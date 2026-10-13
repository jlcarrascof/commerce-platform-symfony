<?php

namespace App\Tests\Functional;

use App\Entity\AuditLog;
use App\Entity\Order;
use App\Entity\Product;
use App\Entity\User;
use App\Message\OrderConfirmed;
use App\MessageHandler\OrderConfirmedHandler;
use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;

class OrderConfirmedAsyncTest extends WebTestCase
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
        $container = static::getContainer();
        $product = $container->get('doctrine')->getRepository(Product::class)->findOneBy(['slug' => $slug]);

        return $product->getId();
    }

    public function testConfirmingAnOrderDispatchesOrderConfirmedMessage(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'alice@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode(['items' => [['productId' => $this->productIdBySlug('yoga-mat'), 'quantity' => 1]]]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $messages = array_map(static fn ($envelope) => $envelope->getMessage(), $transport->getSent());
        $orderConfirmedMessages = array_filter($messages, static fn ($message) => $message instanceof OrderConfirmed);

        self::assertNotEmpty($orderConfirmedMessages, 'Confirming an order should dispatch an OrderConfirmed message.');
        $dispatched = array_values($orderConfirmedMessages)[count($orderConfirmedMessages) - 1];
        self::assertSame($order['id'], $dispatched->getOrderId());
    }

    public function testHandlerWritesEmailSentAuditLogEntry(): void
    {
        $client = static::createClient();
        $token = $this->loginAs($client, 'bob@example.com');

        $client->request('POST', '/api/orders', [], [], [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_AUTHORIZATION' => "Bearer $token",
        ], json_encode(['items' => [['productId' => $this->productIdBySlug('knife-set'), 'quantity' => 1]]]));
        $order = json_decode($client->getResponse()->getContent(), true);

        $client->request('POST', "/api/orders/{$order['id']}/confirm", [], [], ['HTTP_AUTHORIZATION' => "Bearer $token"]);
        self::assertSame(Response::HTTP_OK, $client->getResponse()->getStatusCode());

        $container = static::getContainer();
        $entityManager = $container->get('doctrine')->getManager();
        $orderEntity = $entityManager->getRepository(Order::class)->find($order['id']);
        $bob = $entityManager->getRepository(User::class)->findOneBy(['email' => 'bob@example.com']);

        $auditLogsBefore = $entityManager->getRepository(AuditLog::class)->findBy(['order' => $orderEntity, 'action' => 'email_sent']);
        self::assertCount(0, $auditLogsBefore, 'The email should not be sent synchronously on the request thread.');

        // Process the message directly, the same way the real worker would,
        // without waiting on the handler's artificial sleep(3) via a real queue.
        $handler = $container->get(OrderConfirmedHandler::class);
        $handler(new OrderConfirmed($order['id'], $bob->getId()));

        $auditLogsAfter = $entityManager->getRepository(AuditLog::class)->findBy(['order' => $orderEntity, 'action' => 'email_sent']);
        self::assertCount(1, $auditLogsAfter);
    }
}
