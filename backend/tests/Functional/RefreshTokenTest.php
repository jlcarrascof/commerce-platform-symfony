<?php

namespace App\Tests\Functional;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class RefreshTokenTest extends WebTestCase
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

    private function login(\Symfony\Bundle\FrameworkBundle\KernelBrowser $client, string $email): array
    {
        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => $email,
            'password' => 'password123',
        ]));

        return json_decode($client->getResponse()->getContent(), true);
    }

    public function testLoginResponseIncludesARefreshToken(): void
    {
        $client = static::createClient();
        $data = $this->login($client, 'alice@example.com');

        self::assertArrayHasKey('token', $data);
        self::assertArrayHasKey('refresh_token', $data);
        self::assertNotEmpty($data['refresh_token']);
    }

    public function testRefreshTokenExchangesForANewAccessToken(): void
    {
        $client = static::createClient();
        $login = $this->login($client, 'bob@example.com');

        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $login['refresh_token'],
        ]));

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $refreshed = json_decode($client->getResponse()->getContent(), true);
        self::assertArrayHasKey('token', $refreshed);
        self::assertArrayHasKey('refresh_token', $refreshed);
        self::assertNotSame($login['refresh_token'], $refreshed['refresh_token']);
    }

    public function testNewAccessTokenFromRefreshWorksOnAProtectedEndpoint(): void
    {
        $client = static::createClient();
        $login = $this->login($client, 'carla@example.com');

        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $login['refresh_token'],
        ]));
        $refreshed = json_decode($client->getResponse()->getContent(), true);

        $client->request('GET', '/api/orders', [], [], ['HTTP_AUTHORIZATION' => "Bearer {$refreshed['token']}"]);

        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testUsedRefreshTokenIsRevokedAndCannotBeReused(): void
    {
        $client = static::createClient();
        $login = $this->login($client, 'alice@example.com');

        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $login['refresh_token'],
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        // Reusing the same (now single-use-consumed) refresh token must be rejected.
        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => $login['refresh_token'],
        ]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }

    public function testUnknownRefreshTokenReturns401(): void
    {
        $client = static::createClient();
        $client->request('POST', '/api/token/refresh', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'refresh_token' => 'this-refresh-token-does-not-exist',
        ]));

        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
    }
}
