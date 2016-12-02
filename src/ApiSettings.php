<?php declare(strict_types=1);

namespace ApiClients\Pusher;

use PackageVersions\Versions;

final class ApiSettings
{
    /**
     * @param string $version
     * @return string
     */
    public static function getVersion(string $version = ''): string
    {
        if ($version === '') {
            $version = Versions::getVersion('api-clients/pusher');
        }

        list($version, $hash) = explode('@', $version);

        if (substr($version, -4) === '-dev') {
            return '0.0.1-' . $hash;
        }

        return $version;
    }
}
