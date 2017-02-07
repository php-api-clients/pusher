<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use ApiClients\Client\Pusher\ApiSettings;
use ApiClients\Tools\TestUtilities\TestCase;

final class ApiSettingsTest extends TestCase
{
    public function getVersionProvider()
    {
        yield [
            '9999999-dev@abcdefghijklopqrstuwxyz',
            '0.0.1-abcdefghijklopqrstuwxyz',
        ];

        yield [
            'dev@xyz',
            '0.0.1-xyz',
        ];

        yield [
            '1.0.0@abcdefghijklopqrstuwxyz',
            '1.0.0',
        ];
    }

    /**
     * @dataProvider getVersionProvider
     */
    public function testGetVersion(string $input, string $output)
    {
        $this->assertSame($output, ApiSettings::getVersion($input));
    }

    public function testGetVersionDefault()
    {
        $this->assertTrue(strlen(ApiSettings::getVersion()) > 0);
    }

    public function testCreateUrl()
    {
        $expectedUrl = 'wss://ws.pusherapp.com/app/barBaz?client=api-clients%2Fpusher+%28https%3A%2F%2Fphp-api-clients.org%2Fclients%2Fpusher%29&protocol=7&version=' . ApiSettings::getVersion();

        $this->assertSame($expectedUrl, ApiSettings::createUrl('barBaz'));
    }
}
