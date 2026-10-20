<?php

namespace App\Tenant;

use App\Entity\Tenant;

/**
 * Request-scoped holder for the tenant resolved by TenantResolverSubscriber.
 * Anything that needs "the current tenant" (controllers building new
 * entities, voters, services) reads it from here instead of re-resolving
 * the request header itself.
 */
class TenantContext
{
    private ?Tenant $tenant = null;

    public function getTenant(): ?Tenant
    {
        return $this->tenant;
    }

    public function setTenant(?Tenant $tenant): void
    {
        $this->tenant = $tenant;
    }
}
