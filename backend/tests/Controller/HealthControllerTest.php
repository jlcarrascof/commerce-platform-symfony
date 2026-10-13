<?php

namespace App\Tests\Controller;

use App\Controller\HealthController;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;

class HealthControllerTest extends TestCase
{
    public function testReturnsOkWhenTheDatabaseIsReachable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeQuery')->with('SELECT 1');

        $controller = new HealthController($connection);
        $response = $controller();

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"ok","database":"reachable"}',
            (string) $response->getContent(),
        );
    }

    public function testReturnsServiceUnavailableWhenTheDatabaseIsUnreachable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects(self::once())->method('executeQuery')->willThrowException(
            new \RuntimeException('Connection refused'),
        );

        $controller = new HealthController($connection);
        $response = $controller();

        self::assertSame(Response::HTTP_SERVICE_UNAVAILABLE, $response->getStatusCode());
        self::assertJsonStringEqualsJsonString(
            '{"status":"error","database":"unreachable"}',
            (string) $response->getContent(),
        );
    }
}
