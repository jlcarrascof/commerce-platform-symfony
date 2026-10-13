<?php

namespace App\Tests\Functional;

use App\Controller\ProductController;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Request;

/**
 * These tests call ProductController::list()/create()/delete() directly as
 * plain PHP method calls instead of through the HTTP test client. Symfony's
 * functional test client resets all "resettable" services (which includes
 * cache pools) after every single simulated HTTP request, by design, to keep
 * requests isolated from each other — which would make every request look
 * like a cache MISS regardless of whether caching actually works. Calling the
 * controller directly keeps everything in one request-reset cycle, so the
 * cache pool genuinely persists between calls, the same way it does for two
 * real HTTP requests hitting the same running application.
 */
class ProductCatalogCacheTest extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer()->get('test.service_container');
        $entityManager = $container->get('doctrine')->getManager();

        (new ORMPurger($entityManager))->purge();

        $referenceRepository = new ReferenceRepository($entityManager);

        foreach ([
            \App\DataFixtures\CategoryProductFixtures::class,
            \App\DataFixtures\CustomerUserFixtures::class,
        ] as $fixtureClass) {
            $fixture = $container->get($fixtureClass);
            $fixture->setReferenceRepository($referenceRepository);
            $fixture->load($entityManager);
        }
    }

    public function testFirstRequestIsAMissAndTheIdenticalNextOneIsAHit(): void
    {
        $controller = static::getContainer()->get(ProductController::class);

        $first = $controller->list(new Request(['limit' => '5', 'sort' => 'price']));
        self::assertSame('MISS', $first->headers->get('X-Cache'));

        $second = $controller->list(new Request(['limit' => '5', 'sort' => 'price']));
        self::assertSame('HIT', $second->headers->get('X-Cache'));
    }

    public function testDifferentQueryParametersAreCachedSeparately(): void
    {
        $controller = static::getContainer()->get(ProductController::class);

        $byName = $controller->list(new Request(['limit' => '3', 'sort' => 'name']));
        self::assertSame('MISS', $byName->headers->get('X-Cache'));

        $byNameDesc = $controller->list(new Request(['limit' => '3', 'sort' => '-name']));
        self::assertSame('MISS', $byNameDesc->headers->get('X-Cache'), 'A different sort order is a different cache key.');
    }

    public function testCreatingAProductInvalidatesTheCache(): void
    {
        $container = static::getContainer();
        $controller = $container->get(ProductController::class);
        $categoryId = $this->firstCategoryId();

        $first = $controller->list(new Request(['limit' => '100']));
        self::assertSame('MISS', $first->headers->get('X-Cache'));
        $firstBody = json_decode((string) $first->getContent(), true);
        self::assertNotContains('Cache Invalidation Widget', array_column($firstBody, 'name'));

        $second = $controller->list(new Request(['limit' => '100']));
        self::assertSame('HIT', $second->headers->get('X-Cache'));

        $createRequest = new Request(content: json_encode([
            'name' => 'Cache Invalidation Widget',
            'slug' => 'cache-invalidation-widget',
            'description' => 'Exists only to prove cache invalidation works.',
            'priceInCents' => 500,
            'stock' => 1,
            'imageUrl' => 'https://picsum.photos/seed/cache-invalidation-widget/400/300',
            'categoryId' => $categoryId,
        ]));
        $createResponse = $controller->create($createRequest);
        self::assertSame(201, $createResponse->getStatusCode());

        $third = $controller->list(new Request(['limit' => '100']));
        self::assertSame('MISS', $third->headers->get('X-Cache'), 'Creating a product must invalidate the cached listing.');
        $thirdBody = json_decode((string) $third->getContent(), true);
        self::assertContains('Cache Invalidation Widget', array_column($thirdBody, 'name'));
    }

    public function testDeletingAProductInvalidatesTheCache(): void
    {
        $controller = static::getContainer()->get(ProductController::class);

        $first = $controller->list(new Request(['limit' => '100']));
        $products = json_decode((string) $first->getContent(), true);
        $productId = $products[0]['id'];

        $second = $controller->list(new Request(['limit' => '100']));
        self::assertSame('HIT', $second->headers->get('X-Cache'));

        $deleteResponse = $controller->delete($productId);
        self::assertSame(204, $deleteResponse->getStatusCode());

        $third = $controller->list(new Request(['limit' => '100']));
        self::assertSame('MISS', $third->headers->get('X-Cache'), 'Deleting a product must invalidate the cached listing.');
        $thirdBody = json_decode((string) $third->getContent(), true);
        self::assertNotContains($productId, array_column($thirdBody, 'id'));
    }

    private function firstCategoryId(): int
    {
        $container = static::getContainer();
        $category = $container->get('doctrine')->getRepository(\App\Entity\Category::class)->findAll()[0];

        return $category->getId();
    }
}
