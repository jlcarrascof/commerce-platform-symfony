<?php

namespace App\Tests\Functional;

use App\Entity\Product;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class SoftDeleteTest extends WebTestCase
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

    private function productIdBySlug(KernelBrowser $client, string $slug): int
    {
        $container = static::getContainer();
        $product = $container->get('doctrine')->getRepository(Product::class)->findOneBy(['slug' => $slug]);

        return $product->getId();
    }

    public function testDeletedProductDisappearsFromPublicCatalog(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $productId = $this->productIdBySlug($client, 'yoga-mat');

        $client->request('DELETE', "/api/products/$productId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/products?limit=100');
        $products = json_decode($client->getResponse()->getContent(), true);
        self::assertNotContains('yoga-mat', array_column($products, 'slug'));
    }

    public function testNonAdminCannotDeleteAProduct(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');
        $productId = $this->productIdBySlug($client, 'running-shoes');

        $client->request('DELETE', "/api/products/$productId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/products?limit=100');
        $products = json_decode($client->getResponse()->getContent(), true);
        self::assertContains('running-shoes', array_column($products, 'slug'));
    }

    public function testDeletedProductAppearsInDeletedListAndCanBeRestored(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $productId = $this->productIdBySlug($client, 'desk-organizer');

        $client->request('DELETE', "/api/products/$productId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/products/deleted', [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        $deleted = json_decode($client->getResponse()->getContent(), true);
        self::assertContains('desk-organizer', array_column($deleted, 'slug'));

        $client->request('POST', "/api/products/$productId/restore", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('GET', '/api/products?limit=100');
        $products = json_decode($client->getResponse()->getContent(), true);
        self::assertContains('desk-organizer', array_column($products, 'slug'));

        $client->request('GET', '/api/products/deleted', [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        $deletedAfterRestore = json_decode($client->getResponse()->getContent(), true);
        self::assertNotContains('desk-organizer', array_column($deletedAfterRestore, 'slug'));
    }

    public function testHistoricalOrderStaysReadableAfterItsProductIsDeleted(): void
    {
        $client = static::createClient();
        $customerToken = $this->loginAs($client, 'carla@example.com');
        $productId = $this->productIdBySlug($client, 'throw-blanket');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $customerToken",
        ], json_encode(['items' => [['productId' => $productId, 'quantity' => 1]]]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $order = json_decode($client->getResponse()->getContent(), true);

        $adminToken = $this->loginAs($client, 'admin@example.com');
        $client->request('DELETE', "/api/products/$productId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        // This is the regression this checkpoint must never reintroduce: the
        // soft_deleteable filter also intercepts lazy Product proxy loading,
        // so without explicitly disabling it around order serialization this
        // request used to crash with a 500 instead of returning the order.
        $client->request('GET', "/api/orders/{$order['id']}", [], [], ['HTTP_AUTHORIZATION' => "Bearer $customerToken"]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $fetched = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Throw Blanket', $fetched['items'][0]['productName']);
        self::assertSame($productId, $fetched['items'][0]['productId']);
    }

    public function testConfirmingAnOrderStillAdjustsStockAfterItsProductIsDeleted(): void
    {
        $client = static::createClient();
        $customerToken = $this->loginAs($client, 'bob@example.com');
        $productId = $this->productIdBySlug($client, 'notebook-set');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $customerToken",
        ], json_encode(['items' => [['productId' => $productId, 'quantity' => 1]]]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $adminToken = $this->loginAs($client, 'admin@example.com');
        $client->request('DELETE', "/api/products/$productId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], ['HTTP_AUTHORIZATION' => "Bearer $customerToken"]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $confirmed = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('confirmed', $confirmed['status']);
    }
}
