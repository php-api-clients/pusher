<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher\CommandBus\Handler;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\CommandBus\Command\SharedAppClientCommand;
use ApiClients\Client\Pusher\CommandBus\Handler\SharedAppClientHandler;
use ApiClients\Tools\TestUtilities\TestCase;
use React\EventLoop\Factory;
use function Clue\React\Block\await;

class SharedAppClientHandlerTest extends TestCase
{
    public function testHandle()
    {
        $loop = Factory::create();
        $appId = uniqid('app-id-', true);
        $handler = new SharedAppClientHandler($loop);

        $app = await($handler->handle(new SharedAppClientCommand($appId)), $loop);
        $this->assertInstanceOf(AsyncClient::class, await($handler->handle(new SharedAppClientCommand($appId)), $loop));
        $this->assertSame($app, await($handler->handle(new SharedAppClientCommand($appId)), $loop));
        $this->assertNotSame($app, await($handler->handle(new SharedAppClientCommand(md5($appId))), $loop));
    }
}
