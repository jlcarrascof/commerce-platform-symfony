<?php

namespace App\Controller;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class OrderController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
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
