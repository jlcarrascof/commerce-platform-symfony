<?php

namespace App\DataFixtures;

use App\Entity\Tenant;
use App\Entity\TenantPlan;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;

class TenantFixtures extends Fixture
{
    public const TENANT_ACME = 'tenant-acme';
    public const TENANT_GLOBEX = 'tenant-globex';

    public function load(ObjectManager $manager): void
    {
        $acme = new Tenant('Acme Corp', 'acme', TenantPlan::Enterprise);
        $manager->persist($acme);
        $this->addReference(self::TENANT_ACME, $acme);

        $globex = new Tenant('Globex Inc', 'globex', TenantPlan::Pro);
        $manager->persist($globex);
        $this->addReference(self::TENANT_GLOBEX, $globex);

        $manager->flush();
    }
}
