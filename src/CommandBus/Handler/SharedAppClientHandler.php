<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher\CommandBus\Handler;

use ApiClients\Client\Pusher\CommandBus\Command\SharedAppClientCommand;
use ApiClients\Client\Pusher\Service\SharedAppClientService;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;

final class SharedAppClientHandler
{
    /**
     * @var SharedAppClientService
     */
    private $service;

    /**
     * SharedAppClientHandler constructor.
     * @param SharedAppClientService $service
     */
    public function __construct(SharedAppClientService $service)
    {
        $this->service = $service;
    }

    /**
     * @param  SharedAppClientCommand $command
     * @return PromiseInterface
     */
    public function handle(SharedAppClientCommand $command): PromiseInterface
    {
        return resolve($this->service->share($command->getAppId()));
    }
}
