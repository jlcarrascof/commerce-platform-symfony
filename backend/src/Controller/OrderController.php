<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Product;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class OrderController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
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
    public function list(#[CurrentUser] User $user): JsonResponse
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            $orders = $this->entityManager->getRepository(Order::class)->findAll();
        } else {
            $customer = $this->findCustomerFor($user);
            $orders = $customer?->getOrders()->toArray() ?? [];
        }

        return new JsonResponse(array_map($this->serialize(...), $orders));
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
