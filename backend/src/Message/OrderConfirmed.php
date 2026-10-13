<?php

namespace App\Message;

final class OrderConfirmed
{
    public function __construct(
        private readonly int $orderId,
        private readonly int $confirmedByUserId,
    ) {
    }

    public function getOrderId(): int
    {
        return $this->orderId;
    }

    public function getConfirmedByUserId(): int
    {
        return $this->confirmedByUserId;
    }
}
