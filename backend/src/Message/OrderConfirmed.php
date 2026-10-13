<?php

namespace App\Message;

final class OrderConfirmed
{
    public function __construct(
        private readonly int $orderId,
    ) {
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }
}
