<?php

namespace App\Tests\Functional;

use App\Entity\Category;
use App\Entity\Product;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class CatalogUpdateTest extends WebTestCase
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
            \App\DataFixtures\OrderFixtures::class,
        ] as $fixtureClass) {
            $fixture = $container->get($fixtureClass);
            $fixture->setReferenceRepository($referenceRepository);
            $fixture->load($entityManager);
        }

        self::ensureKernelShutdown();
    }

    private function loginAs(KernelBrowser $client, string $email, string $password = 'password123'): string
    {
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => $password,
        ]));

        $data = json_decode($client->getResponse()->getContent(), true);

        return $data['token'];
    }

    private function productIdBySlug(string $slug): int
    {
        $product = static::getContainer()->get('doctrine')->getRepository(Product::class)->findOneBy(['slug' => $slug]);

        return $product->getId();
    }

    private function categoryIdBySlug(string $slug): int
    {
        $category = static::getContainer()->get('doctrine')->getRepository(Category::class)->findOneBy(['slug' => $slug]);

        return $category->getId();
    }

    public function testAdminCanUpdateAProduct(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $productId = $this->productIdBySlug('yoga-mat');

        $client->request('PUT', "/api/products/$productId", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['name' => 'Premium Yoga Mat', 'stock' => 42]));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Premium Yoga Mat', $updated['name']);
        self::assertSame(42, $updated['stock']);

        $client->request('GET', '/api/products?limit=100');
        $products = json_decode($client->getResponse()->getContent(), true);
        self::assertContains('Premium Yoga Mat', array_column($products, 'name'));
    }

    public function testNonAdminCannotUpdateAProduct(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');
        $productId = $this->productIdBySlug('running-shoes');

        $client->request('PUT', "/api/products/$productId", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode(['name' => 'Hacked Name']));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testUpdatingAProductWithAnInvalidPriceFails(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $productId = $this->productIdBySlug('notebook-set');

        $client->request('PUT', "/api/products/$productId", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['priceInCents' => -500]));

        self::assertSame(422, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanUpdateACategory(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $categoryId = $this->categoryIdBySlug('electronics');

        $client->request('PUT', "/api/categories/$categoryId", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['name' => 'Electronics & Gadgets']));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Electronics & Gadgets', $updated['name']);

        // Revert so later tests (and other test classes sharing the seeded
        // category) see the original name.
        $client->request('PUT', "/api/categories/$categoryId", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['name' => 'Electronics']));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testNonAdminCannotUpdateACategory(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');
        $categoryId = $this->categoryIdBySlug('toys-games');

        $client->request('PUT', "/api/categories/$categoryId", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode(['name' => 'Hacked Category']));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testSearchingProductsByNameFiltersResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/products', ['search' => 'yoga']);
        $products = json_decode($client->getResponse()->getContent(), true);

        self::assertNotEmpty($products);
        foreach ($products as $product) {
            self::assertStringContainsStringIgnoringCase('yoga', $product['name']);
        }
    }

    public function testSearchingProductsIsCaseInsensitiveAndIgnoresUnmatchedTerms(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/products', ['search' => 'YOGA']);
        self::assertNotEmpty(json_decode($client->getResponse()->getContent(), true));

        $client->request('GET', '/api/products', ['search' => 'this-does-not-exist']);
        self::assertSame([], json_decode($client->getResponse()->getContent(), true));
    }

    public function testSearchingCategoriesByNameFiltersResults(): void
    {
        $client = static::createClient();

        $client->request('GET', '/api/categories', ['search' => 'electro']);
        $categories = json_decode($client->getResponse()->getContent(), true);

        self::assertNotEmpty($categories);
        foreach ($categories as $category) {
            self::assertStringContainsStringIgnoringCase('electro', $category['name']);
        }
    }
}
