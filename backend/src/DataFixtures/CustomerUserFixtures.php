<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerUserFixtures extends Fixture
{
    public const CUSTOMER_1 = 'customer-1';
    public const CUSTOMER_2 = 'customer-2';
    public const CUSTOMER_3 = 'customer-3';
    public const ADMIN_USER = 'admin-user';

    public function __construct(private readonly UserPasswordHasherInterface $passwordHasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User('admin@example.com', '');
        $admin->setPassword($this->passwordHasher->hashPassword($admin, 'password123'));
        $admin->setRoles(['ROLE_ADMIN']);
        $manager->persist($admin);
        $this->addReference(self::ADMIN_USER, $admin);

        $customersData = [
            [self::CUSTOMER_1, 'Alice Johnson', 'alice@example.com'],
            [self::CUSTOMER_2, 'Bob Martinez', 'bob@example.com'],
            [self::CUSTOMER_3, 'Carla Dos Santos', 'carla@example.com'],
        ];

        foreach ($customersData as [$reference, $fullName, $email]) {
            $user = new User($email, '');
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $manager->persist($user);

            $customer = new Customer($fullName, $user);
            $manager->persist($customer);
            $this->addReference($reference, $customer);
        }

        $manager->flush();
    }
}
