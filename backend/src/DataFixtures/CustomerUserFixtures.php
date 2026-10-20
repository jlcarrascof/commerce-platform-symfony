<?php

namespace App\DataFixtures;

use App\Entity\Customer;
use App\Entity\Tenant;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class CustomerUserFixtures extends Fixture implements DependentFixtureInterface
{
    public const CUSTOMER_1 = 'customer-1';
    public const CUSTOMER_2 = 'customer-2';
    public const CUSTOMER_3 = 'customer-3';
    public const CUSTOMER_GLOBEX_1 = 'customer-globex-1';
    public const CUSTOMER_GLOBEX_2 = 'customer-globex-2';
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

        /** @var Tenant $acme */
        $acme = $this->getReference(TenantFixtures::TENANT_ACME, Tenant::class);
        /** @var Tenant $globex */
        $globex = $this->getReference(TenantFixtures::TENANT_GLOBEX, Tenant::class);

        $customersData = [
            [self::CUSTOMER_1, 'Alice Johnson', 'alice@example.com', $acme],
            [self::CUSTOMER_2, 'Bob Martinez', 'bob@example.com', $acme],
            [self::CUSTOMER_3, 'Carla Dos Santos', 'carla@example.com', $acme],
            [self::CUSTOMER_GLOBEX_1, 'Diana Prince', 'diana@globex.example.com', $globex],
            [self::CUSTOMER_GLOBEX_2, 'Erik Lehnsherr', 'erik@globex.example.com', $globex],
        ];

        foreach ($customersData as [$reference, $fullName, $email, $tenant]) {
            $user = new User($email, '');
            $user->setPassword($this->passwordHasher->hashPassword($user, 'password123'));
            $manager->persist($user);

            $customer = new Customer($fullName, $user, $tenant);
            $manager->persist($customer);
            $this->addReference($reference, $customer);
        }

        $manager->flush();
    }

    /** @return array<class-string<Fixture>> */
    public function getDependencies(): array
    {
        return [TenantFixtures::class];
    }
}
