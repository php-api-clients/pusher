<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher\CommandBus\Handler;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\CommandBus\Command\SharedAppClientCommand;
use ApiClients\Client\Pusher\CommandBus\Handler\SharedAppClientHandler;
use ApiClients\Client\Pusher\Service\SharedAppClientService;
use ApiClients\Tools\TestUtilities\TestCase;
use function EventLoop\getLoop;
use function Clue\React\Block\await;

final class SharedAppClientHandlerTest extends TestCase
{
    public function testHandle()
    {
        $loop = getLoop();
        $appId = uniqid('app-id-', true);
        $handler = new SharedAppClientHandler(new SharedAppClientService($loop));

        $app = await($handler->handle(new SharedAppClientCommand($appId)), $loop);
        self::assertInstanceOf(AsyncClient::class, await($handler->handle(new SharedAppClientCommand($appId)), $loop));
        self::assertSame($app, await($handler->handle(new SharedAppClientCommand($appId)), $loop));
        self::assertNotSame($app, await($handler->handle(new SharedAppClientCommand(md5($appId))), $loop));
    }
}
