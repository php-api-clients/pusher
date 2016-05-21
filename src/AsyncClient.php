<?php
declare(strict_types=1);

namespace WyriHaximus\Pusher;

use Ratchet\Client\Connector;
use Ratchet\Client\WebSocket;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;
use Rx\Observable;
use Rx\ObservableInterface;
use Rx\ObserverInterface;
use Rx\React\Promise;
use WyriHaximus\ApiClient\Transport\Client as Transport;
use WyriHaximus\ApiClient\Transport\Factory;
use function React\Promise\resolve;

class AsyncClient
{
    protected $transport;
    protected $app;
    protected $url;
    protected $socket;
    protected $connection;
    protected $channels = [];

    public function __construct(LoopInterface $loop, string $app, Transport $transport = null)
    {
        /*if (!($transport instanceof Transport)) {
            $transport = Factory::create($loop, [
                    'resource_namespace' => 'Async',
                ] + ApiSettings::TRANSPORT_OPTIONS);
        }
        $this->transport = $transport;*/

        $this->app = $app;

        $this->url = 'wss://ws.pusherapp.com:443/app/' .
            $this->app .
            '?client=wyrihaximus-php-pusher-client&version=0.0.1&protocol=7'
        ;
        $connector = new Connector($loop);
        $this->socket = $connector($this->url);
        $this->connection = Promise::toObservable($this->socket)
            ->flatMap(function (WebSocket $socket) {
                $this->socket = resolve($socket);
                return Observable::create(function (ObserverInterface $observer) use ($socket) {
                    $socket->on('message', function (MessageInterface $message) use ($observer) {
                        $observer->onNext($message);
                    });
                    $socket->on('close', function ($code, $reason) {

                    });
                });
            })->map(function (MessageInterface $message) {
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
        $this->socket->then(function (WebSocket $socket) use ($message) {
            $socket->send(json_encode($message));
        });
    }
}
