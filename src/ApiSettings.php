<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use PackageVersions\Versions;

final class ApiSettings
{
    /**
     * Create Pusher compatible version.
     *
     * @param  string $version
     * @return string
     */
    public static function getVersion(string $version = ''): string
    {
        if ($version === '') {
            $version = Versions::getVersion('api-clients/pusher');
        }

        list($version, $hash) = explode('@', $version);

        if (strpos($version, 'dev') !== false) {
            return '0.0.1-' . $hash;
        }

        return $version;
    }

    /**
     * Create WebSocket URL for given App ID.
     *
     * @param  string $appId
     * @return string
     */
    public static function createUrl(string $appId): string
    {
        $query = [
            'client' => 'api-clients/pusher (https://php-api-clients.org/clients/pusher)',
            'protocol' => 7,
            'version' => ApiSettings::getVersion(),
        ];

        return 'wss://ws.pusherapp.com/app/' .
            $appId .
            '?' . http_build_query($query)
        ;
    }
}
