<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher\Service;

use ApiClients\Client\Pusher\AsyncClient;
use React\EventLoop\LoopInterface;
use React\Promise\CancellablePromiseInterface;
use function React\Promise\resolve;

final class SharedAppClientService
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
     * @param  string|null                 $appId
     * @return CancellablePromiseInterface
     */
    public function share(string $appId): CancellablePromiseInterface
    {
        if (isset($this->apps[$appId])) {
            return resolve($this->apps[$appId]);
        }

        $this->apps[$appId] = AsyncClient::create($this->loop, $appId);

        return resolve($this->apps[$appId]);
    }
}
