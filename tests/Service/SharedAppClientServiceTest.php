<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher\Service;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\Service\SharedAppClientService;
use ApiClients\Tools\TestUtilities\TestCase;
use function EventLoop\getLoop;
use function Clue\React\Block\await;

final class SharedAppClientServiceTest extends TestCase
{
    public function testHandle()
    {
        $loop = getLoop();
        $appId = uniqid('app-id-', true);
        $handler = new SharedAppClientService($loop);

        $app = await($handler->handle($appId), $loop);
        self::assertInstanceOf(AsyncClient::class, await($handler->handle($appId), $loop));
        self::assertSame($app, await($handler->handle($appId), $loop));
        self::assertNotSame($app, await($handler->handle(md5($appId)), $loop));
    }
}
