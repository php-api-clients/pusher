<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher\Service;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Foundation\Service\ServiceInterface;
use React\EventLoop\LoopInterface;
use React\Promise\CancellablePromiseInterface;
use function React\Promise\resolve;
use function WyriHaximus\React\futureFunctionPromise;

final class SharedAppClientService implements ServiceInterface
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
     * @param string|null $appId
     * @return CancellablePromiseInterface
     */
    public function handle(string $appId = null): CancellablePromiseInterface
    {
        if (isset($this->apps[$appId])) {
            return resolve($this->apps[$appId]);
        }

        $this->apps[$appId] = new AsyncClient($this->loop, $appId);
        return resolve($this->apps[$appId]);
    }
}
