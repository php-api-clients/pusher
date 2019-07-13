<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher\Service;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\Service\SharedAppClientService;
use ApiClients\Tools\TestUtilities\TestCase;
use function Clue\React\Block\await;
use function EventLoop\getLoop;

/**
 * @internal
 */
final class SharedAppClientServiceTest extends TestCase
{
    public function testHandle(): void
    {
        $loop = getLoop();
        $appId = \uniqid('app-id-', true);
        $handler = new SharedAppClientService($loop);

        $app = await($handler->share($appId), $loop);
        self::assertInstanceOf(AsyncClient::class, $app);
        self::assertSame($app, await($handler->share($appId), $loop));
        self::assertNotSame($app, await($handler->share(\md5($appId)), $loop));
    }
}
