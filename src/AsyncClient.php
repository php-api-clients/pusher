<?php
declare(strict_types=1);

namespace WyriHaximus\Pusher;

use React\EventLoop\LoopInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObservableInterface;
use Rx\Observer\CallbackObserver;
use Rx\ObserverInterface;
use Rx\React\Promise;
use Rx\Scheduler\EventLoopScheduler;
use Rx\SchedulerInterface;
use Rx\Websocket\Client;
use Rx\Websocket\MessageSubject;
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
    protected $messages;
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
        //Only create one connection and share the most recent among all subscriber
        $this->client   = (new Client($this->url))->shareReplay(1);
        $this->messages = $this->client
            ->flatMap(function (MessageSubject $ms) {
                return $ms;
            })
            ->map('json_decode');
    }

    public function channel(string $channel): ObservableInterface
    {
        $channelMessages = $this->messages->filter(function ($event) use ($channel) {
            return isset($event->channel) && $event->channel == $channel;
        });

        $events = Observable::create(function (
            ObserverInterface $observer,
            SchedulerInterface $scheduler
        ) use (
            $channel,
            $channelMessages
        ) {

            $subscription = $channelMessages
                ->filter(function ($msg) {
                    return $msg->event !== 'pusher_internal:subscription_succeeded';
                })
                ->subscribe($observer, $scheduler);

            $this->send(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel]]);

            return new CallbackDisposable(function () use ($channel, $subscription) {
                $this->send(['event' => 'pusher:unsubscribe', 'data' => ['channel' => $channel]]);
                $subscription->dispose();
            });
        });

        return $events->share();
    }

    public function send(array $message)
    {
        $this->client
            ->take(1)
            ->subscribeCallback(function (MessageSubject $ms) use ($message) {
                $ms->send(json_encode($message));
            });
    }
}
