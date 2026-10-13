<?php

namespace App\Tests\Functional;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class AuthenticationTest extends WebTestCase
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

        self::ensureKernelShutdown();
    }

    public function testLoginWithCorrectCredentialsReturnsToken(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $data = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $data);
    }

    public function testLoginWithWrongPasswordReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alice@example.com',
            'password' => 'wrong-password',
        ]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testLoginWithUnknownEmailReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'nobody@example.com',
            'password' => 'password123',
        ]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testProtectedEndpointWithoutTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/orders');

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testProtectedEndpointWithMalformedTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/orders', [], [], ['HTTP_AUTHORIZATION' => 'Bearer not-a-real-jwt']);

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testPublicEndpointDoesNotRequireAToken(): void
    {
        $client = static::createClient();
        $client->request('GET', '/api/products');

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testCustomerCannotCreateProducts(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]));
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([
            'name' => 'Sneaky Product',
            'slug' => 'sneaky-product',
            'description' => 'Should not be creatable by a customer.',
            'priceInCents' => 1000,
            'stock' => 5,
            'imageUrl' => 'https://example.com/image.png',
            'categoryId' => 1,
        ]));

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanCreateProducts(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'admin@example.com',
            'password' => 'password123',
        ]));
        $token = json_decode($client->getResponse()->getContent(), true)['token'];

        $container = static::getContainer();
        $categoryId = $container->get('doctrine')->getRepository(\App\Entity\Category::class)
            ->findOneBy(['slug' => 'electronics'])
            ->getId();

        $client->request('POST', '/api/products', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode([
            'name' => 'Admin Created Product',
            'slug' => 'admin-created-product',
            'description' => 'Created by an admin in a test.',
            'priceInCents' => 1000,
            'stock' => 5,
            'imageUrl' => 'https://example.com/image.png',
            'categoryId' => $categoryId,
        ]));

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
    }
}
