<?php

namespace App\Tests\Functional;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class UserControllerTest extends WebTestCase
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

    private function userIdByEmail(KernelBrowser $client, string $adminToken, string $email): int
    {
        $client->request('GET', '/api/users?limit=100', [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        $users = json_decode($client->getResponse()->getContent(), true);

        foreach ($users as $user) {
            if ($user['email'] === $email) {
                return $user['id'];
            }
        }

        throw new \RuntimeException("User with email $email not found.");
    }

    public function testNonAdminCannotListUsers(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');

        $client->request('GET', '/api/users', [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);

        self::assertSame(Response::HTTP_FORBIDDEN, $client->getResponse()->getStatusCode());
    }

    public function testAdminCanCreateAnAdminUser(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');

        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['email' => 'new-admin@example.com', 'password' => 'test1234', 'isAdmin' => true]));

        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $created = json_decode($client->getResponse()->getContent(), true);
        self::assertTrue($created['isAdmin']);
        self::assertNull($created['fullName']);

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'new-admin@example.com',
            'password' => 'test1234',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testCreatingAUserWithAShortPasswordFails(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');

        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['email' => 'short-pw@example.com', 'password' => 'abc123', 'isAdmin' => true]));

        self::assertSame(422, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('password', $body['errors'][0]['field']);
    }

    public function testCreatingACustomerWithoutFullNameFails(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');

        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['email' => 'no-name@example.com', 'password' => 'test1234', 'isAdmin' => false]));

        self::assertSame(422, $client->getResponse()->getStatusCode());
        $body = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('fullName', $body['errors'][0]['field']);
    }

    public function testAdminCanCreateAndUpdateACustomerUser(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');

        $client->request('POST', '/api/users', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode([
            'email' => 'dave@example.com',
            'password' => 'test1234',
            'isAdmin' => false,
            'fullName' => 'Dave Example',
        ]));
        self::assertSame(Response::HTTP_CREATED, $client->getResponse()->getStatusCode());
        $created = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Dave Example', $created['fullName']);

        $client->request('PUT', "/api/users/{$created['id']}", [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $adminToken",
        ], json_encode(['fullName' => 'Dave Updated', 'isAdmin' => false]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
        $updated = json_decode($client->getResponse()->getContent(), true);
        self::assertSame('Dave Updated', $updated['fullName']);
    }

    public function testDeactivatedUserCannotLogInAndRestoreReenablesIt(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $userId = $this->userIdByEmail($client, $adminToken, 'bob@example.com');

        $client->request('DELETE', "/api/users/$userId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_NO_CONTENT, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]));
        self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());

        $client->request('POST', "/api/users/$userId/restore", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'bob@example.com',
            'password' => 'password123',
        ]));
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());
    }

    public function testAdminCannotDeactivateTheirOwnAccount(): void
    {
        $client = static::createClient();
        $adminToken = $this->loginAs($client, 'admin@example.com');
        $adminId = $this->userIdByEmail($client, $adminToken, 'admin@example.com');

        $client->request('DELETE', "/api/users/$adminId", [], [], ['HTTP_AUTHORIZATION' => "Bearer $adminToken"]);

        self::assertSame(422, $client->getResponse()->getStatusCode());
    }
}
