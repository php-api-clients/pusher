<?php declare(strict_types=1);

namespace ApiClients\Pusher;

use React\EventLoop\Factory;
use React\Promise\Deferred;
use Rx\Observer\CallbackObserver;
use function Clue\React\Block\await;

class Client
{
    protected $loop;
    protected $client;

    public function __construct(string $app)
    {
        $this->loop = Factory::create();
        $this->client = new AsyncClient($this->loop, $app);
    }

    public function channel(string $channel, callable $listener)
    {
        $this->channels(
            [
                $channel,
            ],
            $listener
        );
    }

    public function channels(array $channels, callable $listener)
    {
        $deferred = new Deferred();
        foreach ($channels as $channel) {
            $this->client->channel($channel)->subscribe(
                new CallbackObserver(
                    $listener,
                    null,
                    function () use ($deferred) {
                        $deferred->resolve();
                    }
                )
            );
        }
        await(
            $deferred->promise(),
            $this->loop
        );
    }
}
