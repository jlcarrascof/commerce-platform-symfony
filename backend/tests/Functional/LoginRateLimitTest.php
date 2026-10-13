<?php

namespace App\Tests\Functional;

use Doctrine\Common\DataFixtures\Purger\ORMPurger;
use Doctrine\Common\DataFixtures\ReferenceRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Response;

class LoginRateLimitTest extends WebTestCase
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

    public static function tearDownAfterClass(): void
    {
        // Clear the throttling counters this test deliberately tripped, so the
        // lockout doesn't bleed into other test classes reusing the same emails.
        $kernel = self::bootKernel();
        $kernel->getContainer()->get('test.service_container')->get('cache.rate_limiter')->clear();
        self::ensureKernelShutdown();
    }

    public function testSixthLoginAttemptWithinAMinuteIsThrottled(): void
    {
        $client = static::createClient();

        for ($i = 0; $i < 5; ++$i) {
            $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
                'email' => 'alice@example.com',
                'password' => 'wrong-password',
            ]));
            self::assertSame(Response::HTTP_UNAUTHORIZED, $client->getResponse()->getStatusCode());
        }

        $client->request('POST', '/api/login', [], [], ['CONTENT_TYPE' => 'application/json'], json_encode([
            'email' => 'alice@example.com',
            'password' => 'password123',
        ]));

        self::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $client->getResponse()->getStatusCode());
    }
}
