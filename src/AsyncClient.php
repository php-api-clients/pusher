<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\EventLoop\Timer\TimerInterface;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler;
use Rx\Websocket\Client as WebsocketClient;
use Rx\Websocket\MessageSubject;
use Throwable;

final class AsyncClient
{
    const NO_ACTIVITY_TIMEOUT = 120;
    const NO_PING_RESPONSE_TIMEOUT = 30;

    /**
     * @var LoopInterface
     */
    protected $loop;

    /**
     * @var WebsocketClient
     */
    protected $lowLevelClient;

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
     * @var int
     */
    protected $delay = 200;

    /**
     * @var TimerInterface
     */
    private $noActivityTimer;

    /**
     * @var TimerInterface
     */
    private $pingIimeoutTimer;

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
            $loop,
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
    public function __construct(LoopInterface $loop, WebsocketClient $client)
    {
        $this->loop = $loop;
        //Only create one connection and share the most recent among all subscriber
        $this->lowLevelClient = $client;
        $this->client = $this->lowLevelClient->retryWhen(function (Observable $errors) {
            echo __LINE__, ': ', time(), PHP_EOL;
            $this->resetActivityTimer();
            return $errors->flatMap(function (Throwable $throwable) {
                return $this->handleLowLevelError($throwable);
            });
        })->shareReplay(1);
        $this->messages = $this->client
            ->flatMap(function (MessageSubject $ms) {
                return $ms;
            })
            ->_ApiClients_jsonDecode()
            ->map(function (array $message) {
                $this->resetActivityTimer();
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
                $this->resetActivityTimer();
                $ms->send(json_encode($message));
            });
    }

    private function handleLowLevelError(Throwable $throwable)
    {
        $this->resetActivityTimer();
        $this->delay *= 2;
        echo get_class($throwable), PHP_EOL;
        echo get_class($throwable->getPrevious()), PHP_EOL;
        echo get_class($throwable->getPrevious()->getPrevious()), PHP_EOL;
        echo get_class($throwable->getPrevious()->getPrevious()->getPrevious()), PHP_EOL;
        echo __LINE__, ': ', time(), PHP_EOL;
        return Observable::timer($this->delay);
    }

    private function resetActivityTimer()
    {
        echo 'resetActivityTimer', PHP_EOL;
        if ($this->noActivityTimer instanceof TimerInterface) {
            $this->noActivityTimer->cancel();
        }

        $this->noActivityTimer = $this->loop->addTimer(
            self::NO_ACTIVITY_TIMEOUT,
            function () {
                echo 'resetActivityTimer:tick', PHP_EOL;
                $this->send(['event' => 'pusher:ping']);
                $this->pingIimeoutTimer = $this->loop->addTimer(
                    self::NO_PING_RESPONSE_TIMEOUT,
                    function () {
                        $this->lowLevelClient->dispose();
                    }
                );
            }
        );
    }
}
