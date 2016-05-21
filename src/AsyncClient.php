<?php
declare(strict_types=1);

namespace WyriHaximus\Pusher;

use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\React\Promise;
use Rx\Scheduler\EventLoopScheduler;
use Rx\Websocket\Client;
use WyriHaximus\ApiClient\Transport\Client as Transport;
use WyriHaximus\ApiClient\Transport\Factory;
use function React\Promise\resolve;
use function EventLoop\getLoop;
use function EventLoop\setLoop;

class AsyncClient
{
    protected $transport;
    protected $app;
    protected $url;
    protected $client;
    protected $connection;
    protected $channels = [];

    public function __construct(LoopInterface $loop, string $app, Transport $transport = null)
    {
        setLoop($loop);
        /*if (!($transport instanceof Transport)) {
            $transport = Factory::create($loop, [
                    'resource_namespace' => 'Async',
                ] + ApiSettings::TRANSPORT_OPTIONS);
        }
        $this->transport = $transport;*/

        $this->app = $app;

        $this->url = 'wss://ws.pusherapp.com/app/' .
            $this->app .
            '?client=wyrihaximus-php-pusher-client&version=0.0.1&protocol=7'
        ;

        $this->client = new Client($this->url);
        $this->connection = Observable::create(function (ObserverInterface $observer) {
            $this->client->subscribe(new CallbackObserver(function ($ms) use ($observer) {
                $ms->subscribe(new CallbackObserver(
                    function ($message) use ($observer) {
                        $observer->onNext($message);
                    }
                ), new EventLoopScheduler(getLoop()));
            }));
        }, new EventLoopScheduler($loop))->map(function ($message) {
            return json_decode((string)$message, true);
        });
    }

    public function subscribe(string $channel): ObservableInterface
    {
        $this->channels[$channel] = $channel;
        $this->send([
            'event' => 'pusher:subscribe',
            'data' => [
                'channel' => $channel,
            ],
        ]);

        return $this->connection->filter(function (array $event) use ($channel) {
            return isset($event['channel']) && $event['channel'] == $channel;
        })->filter(function (array $event) use ($channel) {
            return $event['event'] !== 'pusher_internal:subscription_succeeded';
        });
    }

    public function send(array $message)
    {
        $this->client->send(json_encode($message));
    }
}
