<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher\CommandBus\Command;

use ApiClients\Client\Pusher\CommandBus\Command\SharedAppClientCommand;
use ApiClients\Tools\TestUtilities\TestCase;

/**
 * @internal
 */
final class SharedAppClientCommandTest extends TestCase
{
    public function testGetApp(): void
    {
        $appId = \uniqid('app-id-', true);
        self::assertSame($appId, (new SharedAppClientCommand($appId))->getAppId());
    }
}
