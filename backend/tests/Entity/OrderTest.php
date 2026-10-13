<?php

namespace App\Tests\Entity;

use App\Entity\Category;
use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatus;
use App\Entity\Product;
use App\Entity\User;
use PHPUnit\Framework\TestCase;

class OrderTest extends TestCase
{
    public function testNewOrderStartsAsPending(): void
    {
        $order = new Order($this->makeCustomer());

        self::assertSame(OrderStatus::Pending, $order->getStatus());
    }

    public function testStatusCanTransitionFromPendingToConfirmed(): void
    {
        $order = new Order($this->makeCustomer());

        $order->setStatus(OrderStatus::Confirmed);

        self::assertSame(OrderStatus::Confirmed, $order->getStatus());
    }

    public function testStatusCanTransitionFromConfirmedToCancelled(): void
    {
        $order = new Order($this->makeCustomer());
        $order->setStatus(OrderStatus::Confirmed);

        $order->setStatus(OrderStatus::Cancelled);

        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testAddItemAppendsToItemsCollection(): void
    {
        $order = new Order($this->makeCustomer());
        $product = $this->makeProduct(stock: 10, priceInCents: 1500);

        $item = new OrderItem($order, $product, 2, $product->getPriceInCents());
        $order->addItem($item);

        self::assertCount(1, $order->getItems());
        self::assertSame($item, $order->getItems()->first());
    }

    public function testAddItemDoesNotDuplicateTheSameItem(): void
    {
        $order = new Order($this->makeCustomer());
        $product = $this->makeProduct(stock: 10, priceInCents: 1500);
        $item = new OrderItem($order, $product, 1, $product->getPriceInCents());

        $order->addItem($item);
        $order->addItem($item);

        self::assertCount(1, $order->getItems());
    }

    public function testOrderItemSnapshotsThePriceAtCreationTime(): void
    {
        $order = new Order($this->makeCustomer());
        $product = $this->makeProduct(stock: 10, priceInCents: 1500);

        $item = new OrderItem($order, $product, 1, $product->getPriceInCents());
        $product->setPriceInCents(9999);

        self::assertSame(1500, $item->getUnitPriceInCents());
    }

    public function testInsufficientStockIsDetectedBeforeConfirming(): void
    {
        $product = $this->makeProduct(stock: 1, priceInCents: 1000);
        $order = new Order($this->makeCustomer());
        $order->addItem(new OrderItem($order, $product, 5, $product->getPriceInCents()));

        $hasEnoughStock = true;
        foreach ($order->getItems() as $item) {
            if ($item->getProduct()->getStock() < $item->getQuantity()) {
                $hasEnoughStock = false;
            }
        }

        self::assertFalse($hasEnoughStock);
    }

    public function testConfirmingAnOrderDecrementsProductStock(): void
    {
        $product = $this->makeProduct(stock: 10, priceInCents: 1000);
        $order = new Order($this->makeCustomer());
        $order->addItem(new OrderItem($order, $product, 4, $product->getPriceInCents()));

        foreach ($order->getItems() as $item) {
            $item->getProduct()->setStock($item->getProduct()->getStock() - $item->getQuantity());
        }
        $order->setStatus(OrderStatus::Confirmed);

        self::assertSame(6, $product->getStock());
        self::assertSame(OrderStatus::Confirmed, $order->getStatus());
    }

    public function testCancellingAConfirmedOrderRestoresProductStock(): void
    {
        $product = $this->makeProduct(stock: 6, priceInCents: 1000);
        $order = new Order($this->makeCustomer());
        $order->addItem(new OrderItem($order, $product, 4, $product->getPriceInCents()));
        $order->setStatus(OrderStatus::Confirmed);

        foreach ($order->getItems() as $item) {
            $item->getProduct()->setStock($item->getProduct()->getStock() + $item->getQuantity());
        }
        $order->setStatus(OrderStatus::Cancelled);

        self::assertSame(10, $product->getStock());
        self::assertSame(OrderStatus::Cancelled, $order->getStatus());
    }

    public function testIdempotencyKeyIsNullByDefault(): void
    {
        $order = new Order($this->makeCustomer());

        self::assertNull($order->getIdempotencyKey());
    }

    public function testIdempotencyKeyCanBeSet(): void
    {
        $order = new Order($this->makeCustomer());

        $order->setIdempotencyKey('a-unique-key');

        self::assertSame('a-unique-key', $order->getIdempotencyKey());
    }

    private function makeCustomer(): Customer
    {
        $user = new User('customer@example.com', 'hashed-password');

        return new Customer('Test Customer', $user);
    }

    private function makeProduct(int $stock, int $priceInCents): Product
    {
        $category = new Category('Test Category', 'test-category');

        return new Product(
            name: 'Test Product',
            slug: 'test-product',
            description: 'A product used for testing.',
            priceInCents: $priceInCents,
            stock: $stock,
            imageUrl: 'https://example.com/image.png',
            category: $category,
        );
    }
}
