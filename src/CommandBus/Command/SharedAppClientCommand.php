<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher\CommandBus\Command;

use WyriHaximus\Tactician\CommandHandler\Annotations\Handler;

/**
 * @Handler("ApiClients\Client\Pusher\CommandBus\Handler\SharedAppClientHandler")
 */
final class SharedAppClientCommand
{
    /**
     * @var string
     */
    private $appId;

    /**
     * SharedAppClientCommand constructor.
     * @param string $appId
     */
    public function __construct($appId)
    {
        $this->appId = $appId;
    }

    /**
     * @return string
     */
    public function getAppId()
    {
        return $this->appId;
    }
}
