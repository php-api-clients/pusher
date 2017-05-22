<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use React\Dns\Resolver\Resolver;
use function React\Promise\reject;
use RuntimeException;
use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Tools\TestUtilities\TestCase;
use React\EventLoop\Factory;
use Rx\Observable;
use Rx\Scheduler\ImmediateScheduler;
use Rx\Websocket\Client;

final class AsyncClientTest extends TestCase
{
    public function testCreateFactory()
    {
        $loop = Factory::create();
        $appId = uniqid('app-id-', true);
        self::assertInstanceOf(AsyncClient::class, AsyncClient::create($loop, $appId));
    }

    public function testConnectionError()
    {
        $capturedException = null;
        $error = new RuntimeException();
        $observable = Observable::error($error, new ImmediateScheduler());
        $client = new AsyncClient($observable);
        $client->channel('test')->subscribe(
            function () {},
            function ($e) use (&$capturedException) {
                $capturedException = $e;
            }
        );
        self::assertNull($capturedException);
    }

    public function testConnectionRetry()
    {
        $loop = Factory::create();
        $error = new RuntimeException('', 4199);
        $resolver = $this->prophesize(Resolver::class);
        $resolver->resolve('ws.pusherapp.com')->shouldBeCalled()->willReturn(reject($error));
        $client = AsyncClient::create($loop, 'abc', $resolver->reveal());
        $client->channel('test')->subscribe();
        $loop->addTimer(1, function () {});
        $loop->run();
    }
}
