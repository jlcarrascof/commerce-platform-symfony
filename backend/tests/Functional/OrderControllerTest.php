<?php

namespace App\Tests\Functional;

use App\Entity\Product;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class OrderControllerTest extends WebTestCase
{
    public static function setUpBeforeClass(): void
    {
        $kernel = self::bootKernel();
        $container = $kernel->getContainer()->get('test.service_container');
        $entityManager = $container->get('doctrine')->getManager();

        (new ORMPurger($entityManager))->purge();

        $referenceRepository = new ReferenceRepository($entityManager);

        // Load in explicit dependency order instead of relying on the fixture
        // Loader's auto-resolution, since CustomerUserFixtures needs a constructor
        // argument the Loader cannot provide when instantiating dependencies itself.
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
        $container = static::getContainer();
        $product = $container->get('doctrine')->getRepository(Product::class)->findOneBy(['slug' => $slug]);

        return $product->getId();
    }

    public function testCreateOrderRequiresAuthentication(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/orders', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'items' => [['productId' => $this->productIdBySlug('yoga-mat'), 'quantity' => 1]],
        ]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testCreateOrderWithEmptyItemsReturns422(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode(['items' => []]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    public function testCreateOrderWithUnknownProductReturns422(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode(['items' => [['productId' => 999999, 'quantity' => 1]]]));

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    public function testCustomerCanCreateAndConfirmTheirOwnOrder(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([
            'items' => [['productId' => $this->productIdBySlug('scented-candle-set'), 'quantity' => 2]],
        ]));

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $order = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('pending', $order['status']);

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $confirmed = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('confirmed', $confirmed['status']);
    }

    public function testConfirmingAnOrderWithInsufficientStockReturns422(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'bob@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([
            'items' => [['productId' => $this->productIdBySlug('ergonomic-office-chair'), 'quantity' => 9999]],
        ]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ]);

        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    public function testConfirmingAnAlreadyConfirmedOrderReturns422(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'carla@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([
            'items' => [['productId' => $this->productIdBySlug('puzzle-1000-pieces'), 'quantity' => 1]],
        ]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        self::assertSame(Response::HTTP_UNPROCESSABLE_ENTITY, $client->getResponse()->getStatusCode());
    }

    public function testAnotherCustomerCannotConfirmSomeoneElsesOrder(): void
    {
        $client = static::createClient();
        $ownerToken = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $ownerToken",
        ], json_encode([
            'items' => [['productId' => $this->productIdBySlug('notebook-set'), 'quantity' => 1]],
        ]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $intruderToken = $this->loginAs($client, 'bob@example.com');
        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer $intruderToken",
        ]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanCancelAnyCustomersOrder(): void
    {
        $client = static::createClient();
        $ownerToken = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $ownerToken",
        ], json_encode([
            'items' => [['productId' => $this->productIdBySlug('desk-organizer'), 'quantity' => 1]],
        ]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $adminToken = $this->loginAs($client, 'admin@example.com');
        $client->request('POST', "/api/orders/{$order['id']}/cancel", [], [], [
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $cancelled = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('cancelled', $cancelled['status']);
    }

    public function testShowOrderReturns404ForUnknownId(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');

        $client->request('GET', '/api/orders/999999', [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);

        self::assertSame(Response::HTTP_NOT_FOUND, $client->getResponse()->getStatusCode());
    }

    public function testShowOrderReturns403ForNonOwnerNonAdmin(): void
    {
        $client = static::createClient();
        $ownerToken = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $ownerToken",
        ], json_encode([
            'items' => [['productId' => $this->productIdBySlug('throw-blanket'), 'quantity' => 1]],
        ]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $otherToken = $this->loginAs($client, 'bob@example.com');
        $client->request('GET', "/api/orders/{$order['id']}", [], [], ['HTTP_AUTHORIZATION' => "Bearer $otherToken"]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testIdempotencyKeyPreventsDuplicateOrderCreation(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'carla@example.com');

        $headers = [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
            'HTTP_IDEMPOTENCY_KEY' => 'test-idempotency-key-123',
        ];
        $body = json_encode(['items' => [['productId' => $this->productIdBySlug('remote-control-car'), 'quantity' => 1]]]);

        $client->request('POST', '/api/orders', [], [], $headers, $body);
        $first = json_decode($client->getResponse()->getContent(), true);
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/orders', [], [], $headers, $body);
        $second = json_decode($client->getResponse()->getContent(), true);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        self::assertSame($first['id'], $second['id']);
    }
}
