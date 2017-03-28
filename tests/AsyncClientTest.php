<?php
declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Tools\TestUtilities\TestCase;
use React\EventLoop\Factory;

final class AsyncClientTest extends TestCase
{
    public function testCreateFactory()
    {
        $loop = Factory::create();
        $appId = uniqid('app-id-', true);
        self::assertInstanceOf(AsyncClient::class, AsyncClient::create($loop, $appId));
    }
}
