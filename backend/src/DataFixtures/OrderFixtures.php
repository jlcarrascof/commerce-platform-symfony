<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\OrderStatus;
use App\Entity\Product;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class OrderFixtures extends Fixture implements DependentFixtureInterface
{
    public function load(ObjectManager $manager): void
    {
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_1, 'wireless-mouse', 2, OrderStatus::Confirmed);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_1, 'yoga-mat', 1, OrderStatus::Pending);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_2, 'mechanical-keyboard', 1, OrderStatus::Confirmed);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_3, 'running-shoes', 1, OrderStatus::Cancelled);

        $manager->flush();
    }

    private function createOrder(
        ObjectManager $manager,
        string $customerReference,
        string $productSlug,
        int $quantity,
        OrderStatus $status,
    ): void {
        /** @var Customer $customer */
        $customer = $this->getReference($customerReference, Customer::class);

        $product = $manager->getRepository(Product::class)->findOneBy(['slug' => $productSlug]);

        $order = new Order($customer);
        $order->setStatus($status);
        $manager->persist($order);

        $item = new OrderItem($order, $product, $quantity, $product->getPriceInCents());
        $order->addItem($item);
        $manager->persist($item);
    }

    /** @return array<class-string<Fixture>> */
    public function getDependencies(): array
    {
        return [
            CategoryProductFixtures::class,
            CustomerUserFixtures::class,
        ];
    }
}
