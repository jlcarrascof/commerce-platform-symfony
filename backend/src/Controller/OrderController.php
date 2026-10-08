<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\User;
use App\Security\Voter\OrderVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class OrderController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly AuthorizationCheckerInterface $authorizationChecker,
    ) {
    }

    #[Route('/api/orders', name: 'order_create', methods: ['POST'])]
    public function create(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $customer = $this->findCustomerFor($user);

        if (null === $customer) {
            return new JsonResponse(['error' => 'Only customers can place orders.'], 422);
        }

        $idempotencyKey = $request->headers->get('Idempotency-Key');

        if (null !== $idempotencyKey) {
            $existingOrder = $this->entityManager->getRepository(Order::class)->findOneBy(['idempotencyKey' => $idempotencyKey]);

            if (null !== $existingOrder) {
                return new JsonResponse($this->serialize($existingOrder), 200);
            }
        }

        $data = json_decode($request->getContent(), true) ?? [];
        $items = $data['items'] ?? [];

        if (!is_array($items) || [] === $items) {
            return new JsonResponse(['errors' => [['field' => 'items', 'message' => 'At least one item is required.']]], 422);
        }

        $order = new Order($customer);
        $order->setIdempotencyKey($idempotencyKey);

        foreach ($items as $index => $item) {
            $productId = $item['productId'] ?? null;
            $quantity = $item['quantity'] ?? null;

            $product = is_int($productId) || is_string($productId)
                ? $this->entityManager->getRepository(Product::class)->find($productId)
                : null;

            if (null === $product) {
                return new JsonResponse(['errors' => [['field' => "items[$index].productId", 'message' => 'Product not found.']]], 422);
            }

            if (!is_int($quantity) || $quantity < 1) {
                return new JsonResponse(['errors' => [['field' => "items[$index].quantity", 'message' => 'Quantity must be a positive integer.']]], 422);
            }

            $order->addItem(new OrderItem($order, $product, $quantity, $product->getPriceInCents()));
        }

        $this->entityManager->persist($order);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($order), 201);
    }

    #[Route('/api/orders', name: 'order_list', methods: ['GET'])]
    public function list(Request $request, #[CurrentUser] User $user): JsonResponse
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = min(100, max(1, (int) $request->query->get('limit', 20)));
        $statusParam = $request->query->get('status');

        $qb = $this->entityManager->getRepository(Order::class)->createQueryBuilder('o')
            ->orderBy('o.createdAt', 'DESC');

        if (!in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $customer = $this->findCustomerFor($user);
            if (null === $customer) {
                return new JsonResponse([]);
            }
            $qb->andWhere('o.customer = :customer')->setParameter('customer', $customer);
        }

        if (null !== $statusParam && '' !== $statusParam) {
            $status = OrderStatus::tryFrom($statusParam);
            if (null === $status) {
                return new JsonResponse(['errors' => [['field' => 'status', 'message' => 'Invalid status value.']]], 422);
            }
            $qb->andWhere('o.status = :status')->setParameter('status', $status);
        }

        $totalCount = (clone $qb)->select('COUNT(o.id)')->resetDQLPart('orderBy')->getQuery()->getSingleScalarResult();

        $orders = $qb
            ->setFirstResult(($page - 1) * $limit)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();

        $response = new JsonResponse(array_map($this->serialize(...), $orders));
        $response->headers->set('X-Total-Count', (string) $totalCount);

        return $response;
    }

    #[Route('/api/orders/{id}', name: 'order_show', methods: ['GET'])]
    public function show(int $id, #[CurrentUser] User $user): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found.'], 404);
        }

        $isAdmin = in_array('ROLE_ADMIN', $user->getRoles(), true);
        $isOwner = $order->getCustomer()->getUser()->getId() === $user->getId();

        if (!$isAdmin && !$isOwner) {
            return new JsonResponse(['error' => 'Access denied.'], 403);
        }

        return new JsonResponse($this->serialize($order));
    }

    #[Route('/api/orders/{id}/confirm', name: 'order_confirm', methods: ['POST'])]
    public function confirm(int $id): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found.'], 404);
        }

        if (!$this->authorizationChecker->isGranted(OrderVoter::CONFIRM, $order)) {
            return new JsonResponse(['error' => 'Access denied.'], 403);
        }

        if (OrderStatus::Pending !== $order->getStatus()) {
            return new JsonResponse(['error' => 'Only pending orders can be confirmed.'], 422);
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            if ($product->getStock() < $item->getQuantity()) {
                return new JsonResponse([
                    'error' => sprintf('Insufficient stock for "%s" (requested %d, available %d).', $product->getName(), $item->getQuantity(), $product->getStock()),
                ], 422);
            }
        }

        foreach ($order->getItems() as $item) {
            $product = $item->getProduct();
            $product->setStock($product->getStock() - $item->getQuantity());
        }

        $order->setStatus(OrderStatus::Confirmed);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($order));
    }

    #[Route('/api/orders/{id}/cancel', name: 'order_cancel', methods: ['POST'])]
    public function cancel(int $id): JsonResponse
    {
        $order = $this->entityManager->getRepository(Order::class)->find($id);

        if (null === $order) {
            return new JsonResponse(['error' => 'Order not found.'], 404);
        }

        if (!$this->authorizationChecker->isGranted(OrderVoter::CANCEL, $order)) {
            return new JsonResponse(['error' => 'Access denied.'], 403);
        }

        if (OrderStatus::Cancelled === $order->getStatus()) {
            return new JsonResponse(['error' => 'Order is already cancelled.'], 422);
        }

        if (OrderStatus::Confirmed === $order->getStatus()) {
            foreach ($order->getItems() as $item) {
                $product = $item->getProduct();
                $product->setStock($product->getStock() + $item->getQuantity());
            }
        }

        $order->setStatus(OrderStatus::Cancelled);
        $this->entityManager->flush();

        return new JsonResponse($this->serialize($order));
    }

    private function findCustomerFor(User $user): ?Customer
    {
        return $this->entityManager->getRepository(Customer::class)->findOneBy(['user' => $user]);
    }

    private function serialize(Order $order): array
    {
        return [
            'id' => $order->getId(),
            'status' => $order->getStatus()->value,
            'createdAt' => $order->getCreatedAt()->format(\DateTimeInterface::ATOM),
            'customer' => [
                'id' => $order->getCustomer()->getId(),
                'fullName' => $order->getCustomer()->getFullName(),
            ],
            'items' => array_map(
                static fn ($item) => [
                    'productId' => $item->getProduct()->getId(),
                    'productName' => $item->getProduct()->getName(),
                    'quantity' => $item->getQuantity(),
                    'unitPriceInCents' => $item->getUnitPriceInCents(),
                ],
                $order->getItems()->toArray(),
            ),
        ];
    }
}
