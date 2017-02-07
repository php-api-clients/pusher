<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher\CommandBus\Command;

use ApiClients\Client\Pusher\CommandBus\Command\SharedAppClientCommand;
use ApiClients\Tools\TestUtilities\TestCase;

final class SharedAppClientCommandTest extends TestCase
{
    public function testGetApp()
    {
        $appId = uniqid('app-id-', true);
        $this->assertSame($appId, (new SharedAppClientCommand($appId))->getAppId());
    }
}
