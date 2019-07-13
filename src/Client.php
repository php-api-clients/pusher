<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use function Clue\React\Block\await;
use React\EventLoop\Factory;
use React\EventLoop\LoopInterface;
use Rx\Observable;

final class Client
{
    /**
     * @var LoopInterface
     */
    private $loop;

    /**
     * @var AsyncClient
     */
    private $client;

    /**
     * @param string $app Application ID
     */
    public function __construct(string $app)
    {
        $this->loop = Factory::create();
        $this->client = AsyncClient::create($this->loop, $app);
    }

    /**
     * @param string   $channel  Channel to listen on
     * @param callable $listener Listener to call on new messages
     */
    public function channel(string $channel, callable $listener): void
    {
        $this->channels([$channel], $listener);
    }

    /**
     * @param string[] $channels Channels to listen on
     * @param callable $listener Listener to call on new messages
     */
    public function channels(array $channels, callable $listener): void
    {
        $promise = Observable::fromArray($channels)
            ->flatMap([$this->client, 'channel'])
            ->do($listener)
            ->count()
            ->toPromise();

        await($promise, $this->loop);
    }
}
