<?php
declare(strict_types=1);

namespace ApiClients\Tests\Pusher;

use ApiClients\Pusher\ApiSettings;
use ApiClients\Tools\TestUtilities\TestCase;

class ApiSettingsTest extends TestCase
{
    public function getVersionProvider()
    {
        yield [
            '9999999-dev@abcdefghijklopqrstuwxyz',
            '0.0.1-abcdefghijklopqrstuwxyz',
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
}
