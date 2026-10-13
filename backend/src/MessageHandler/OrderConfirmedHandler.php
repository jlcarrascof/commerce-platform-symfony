<?php

namespace App\MessageHandler;

use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\User;
use App\Message\OrderConfirmed;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class OrderConfirmedHandler
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(OrderConfirmed $message): void
    {
        $order = $this->entityManager->getRepository(Order::class)->find($message->getOrderId());
        $user = $this->entityManager->getRepository(User::class)->find($message->getConfirmedByUserId());

        if (null === $order || null === $user) {
            // The order or user was removed before this message got processed;
            // nothing left to notify about.
            return;
        }

        // Simulates the latency of a real confirmation email provider. This delay
        // is the whole point of this checkpoint: it happens here, out-of-band,
        // never on the HTTP request thread that confirmed the order.
        sleep(3);

        $this->logger->info('Confirmation email sent for order #{orderId}.', ['orderId' => $order->getId()]);

        $this->entityManager->persist(new AuditLog($order, $user, 'email_sent'));
        $this->entityManager->flush();
    }
}
