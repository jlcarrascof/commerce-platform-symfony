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

        // Extra orders so the admin dashboard pagination has more than one page to show.
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_1, '27-inch-monitor', 1, OrderStatus::Pending);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_2, 'usb-c-hub', 2, OrderStatus::Confirmed);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_3, 'notebook-set', 3, OrderStatus::Pending);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_1, 'electric-kettle', 1, OrderStatus::Confirmed);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_2, 'desk-organizer', 2, OrderStatus::Pending);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_3, 'insulated-water-bottle', 1, OrderStatus::Cancelled);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_1, 'knife-set', 1, OrderStatus::Pending);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_2, 'throw-blanket', 2, OrderStatus::Confirmed);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_3, 'adjustable-dumbbells', 1, OrderStatus::Pending);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_1, 'noise-cancelling-headphones', 1, OrderStatus::Confirmed);
        $this->createOrder($manager, CustomerUserFixtures::CUSTOMER_2, 'building-blocks-set', 2, OrderStatus::Pending);

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
