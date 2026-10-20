<?php

namespace App\EventListener;

use App\Entity\Tenant;
use App\Tenant\TenantContext;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * Resolves the tenant for the current request (via the X-Tenant-Slug
 * header, since subdomain routing needs real DNS/host config this repo
 * doesn't have) before any controller or repository runs a query, and
 * enables the tenant_filter SQLFilter with that tenant's id.
 *
 * Fails closed: routes that don't send a valid X-Tenant-Slug get the
 * filter enabled with an impossible id (0) instead of being left
 * unfiltered, so a missing/typo'd header can never leak cross-tenant
 * rows — it just yields empty results.
 */
class TenantResolverSubscriber implements EventSubscriberInterface
{
    private const TENANT_HEADER = 'X-Tenant-Slug';

    /** @var list<string> Routes with no tenant-scoped data — resolving a tenant for them is pointless. */
    private const EXEMPT_PATH_PREFIXES = ['/api/login', '/api/token/refresh', '/api/health', '/api/doc'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TenantContext $tenantContext,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', 8],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $path = $event->getRequest()->getPathInfo();

        foreach (self::EXEMPT_PATH_PREFIXES as $prefix) {
            if (str_starts_with($path, $prefix)) {
                return;
            }
        }

        $slug = $event->getRequest()->headers->get(self::TENANT_HEADER);
        $tenant = null !== $slug
            ? $this->entityManager->getRepository(Tenant::class)->findOneBy(['slug' => $slug])
            : null;

        $filter = $this->entityManager->getFilters()->enable('tenant_filter');
        $filter->setParameter('tenantId', $tenant?->getId() ?? 0);

        $this->tenantContext->setTenant($tenant);
    }
}
