<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler;
use Rx\Websocket\Client as WebsocketClient;
use Rx\Websocket\MessageSubject;
use Throwable;

final class AsyncClient
{
    /**
     * @var Observable\RefCountObservable
     */
    protected $client;

    /**
     * @var Observable\AnonymousObservable
     */
    protected $messages;

    /**
     * @var array
     */
    protected $channels = [];

    /**
     * @param LoopInterface $loop
     * @param string $app Application ID
     * @param Resolver $resolver Optional DNS resolver
     * @return AsyncClient
     */
    public static function create(LoopInterface $loop, string $app, Resolver $resolver = null): AsyncClient
    {
        try {
            Scheduler::setAsyncFactory(function () use ($loop) {
                return new Scheduler\EventLoopScheduler($loop);
            });
        } catch (Throwable $t) {
        }

        return new self(
            new WebsocketClient(
                ApiSettings::createUrl($app),
                false,
                [],
                $loop,
                $resolver
            )
        );
    }

    /**
     * @internal
     */
    public function __construct(WebsocketClient $client)
    {
        //Only create one connection and share the most recent among all subscriber
        $this->client   = $client->retryWhen(function (Observable $errors) {
            return $this->handleLowLevelError($errors);
        })->shareReplay(1);
        $this->messages = $this->client
            ->flatMap(function (MessageSubject $ms) {
                //var_export($ms);
                return $ms;
            })
            ->_ApiClients_jsonDecode()
            ->map(function (array $message) {
                //var_export($message);
                return Event::createFromMessage($message);
            });
    }

    /**
     * Listen on a channel
     *
     * @param string $channel Channel to listen on
     * @return Observable
     */
    public function channel(string $channel): Observable
    {
        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }

        $channelMessages = $this->messages->filter(function (Event $event) use ($channel) {
            return $event->getChannel() !== '' && $event->getChannel() === $channel;
        });

        $events = Observable::create(function (
            ObserverInterface $observer
        ) use (
            $channel,
            $channelMessages
        ) {
            $subscription = $channelMessages
                ->filter(function (Event $event) {
                    return $event->getEvent() !== 'pusher_internal:subscription_succeeded';
                })
                ->subscribe($observer);

            $this->send(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel]]);

            return new CallbackDisposable(function () use ($channel, $subscription) {
                $this->send(['event' => 'pusher:unsubscribe', 'data' => ['channel' => $channel]]);
                $subscription->dispose();
                unset($this->channels[$channel]);
            });
        });

        $this->channels[$channel] = $events->share();
        return $this->channels[$channel];
    }

    /**
     * Send a message through the client
     *
     * @param array $message Message to send, will be json encoded
     */
    public function send(array $message)
    {
        $this->client
            ->take(1)
            ->subscribe(function (MessageSubject $ms) use ($message) {
                $ms->send(json_encode($message));
            });
    }

    private function handleLowLevelError(Observable $errors)
    {
        $stream = $errors->subscribe(
            function (Throwable $throwable) use (&$stream) {
                echo (string)$throwable, PHP_EOL;
            }
        );
        echo __LINE__, ': ', time(), PHP_EOL;
        return $errors->delay(200);
    }
}
