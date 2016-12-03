<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher\CommandBus\Handler;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\CommandBus\Command\SharedAppClientCommand;
use React\EventLoop\LoopInterface;
use React\Promise\PromiseInterface;
use function React\Promise\resolve;
use function WyriHaximus\React\futureFunctionPromise;

final class SharedAppClientHandler
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var array
     */
    private $apps = [];

    /**
     * SharedAppClientHandler constructor.
     * @param LoopInterface $loop
     */
    public function __construct(LoopInterface $loop)
    {
        $this->loop = $loop;
    }

    /**
     * @param SharedAppClientCommand $command
     * @return PromiseInterface
     */
    public function handle(SharedAppClientCommand $command): PromiseInterface
    {
        if (isset($this->apps[$command->getAppId()])) {
            return resolve($this->apps[$command->getAppId()]);
        }

        $this->apps[$command->getAppId()] = new AsyncClient($this->loop, $command->getAppId());
        return resolve($this->apps[$command->getAppId()]);
    }
}
