<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Rx\Disposable\CallbackDisposable;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Scheduler;
use Rx\Websocket\Client as WebsocketClient;
use Rx\Websocket\MessageSubject;
use Rx\Websocket\WebsocketErrorException;
use Throwable;
use function React\Promise\reject;
use function React\Promise\resolve;

final class AsyncClient
{
    const DEFAULT_DELAY = 200;
    const NO_ACTIVITY_TIMEOUT = 120;
    const NO_PING_RESPONSE_TIMEOUT = 30;

    protected $noActivityTimeout = self::NO_ACTIVITY_TIMEOUT;

    /**
     * @var Observable\RefCountObservable
     */
    protected $client;

    /**
     * @var Observable\AnonymousObservable
     */
    protected $messages;

    /**
     * @var MessageSubject
     */
    protected $sendSubject;

    /**
     * @var array
     */
    protected $channels = [];

    /**
     * @var int
     */
    protected $delay = self::DEFAULT_DELAY;

    /**
     * @internal
     */
    public function __construct(Observable $client)
    {
        $this->messages = $client
            // Save this subject for sending stuff
            ->do(function (MessageSubject $ms) {
                $this->sendSubject = $ms;

                // Resubscribe to an channels we where subscribed to when disconnected
                foreach ($this->channels as $channel => $_) {
                    $this->subscribeOnChannel($channel);
                }
            })

            // Make sure if there is a disconnect or something
            // that we unset the sendSubject
            ->finally(function () {
                $this->sendSubject = null;
            })

            ->flatMap(function (MessageSubject $ms) {
                return $ms;
            })

            // This is the ping/timeout functionality
            ->flatMapLatest(function ($x) {
                // this Observable emits the current value immediately
                // if another value comes along, this all gets disposed (because we are using flatMapLatest)
                // before the timeouts start get triggered
                return Observable::never()
                    ->timeout($this->noActivityTimeout * 1000)
                    ->catch(function () use ($x) {
                        // ping (do something that causes incoming stream to get a message)
                        $this->send(['event' => 'pusher:ping']);
                        // this timeout will actually timeout with a TimeoutException - causing
                        //   everything above this to dispose
                        return Observable::never()->timeout(self::NO_PING_RESPONSE_TIMEOUT * 1000);
                    })
                    ->startWith($x);
            })

            // Decode JSON
            ->_ApiClients_jsonDecode()

            // Deal with connection established messages
            ->flatMap(function (array $message) {
                $this->delay = self::DEFAULT_DELAY;

                $event = Event::createFromMessage($message);

                if ($event->getEvent() === 'pusher:error') {
                    return Observable::fromPromise(reject(
                        new PusherErrorException($event->getData()['message'], $event->getData()['code'])
                    ));
                }

                if ($event->getEvent() === 'pusher:connection_established') {
                    $this->setActivityTimeout($event);
                }

                return Observable::fromPromise(resolve($event));
            })

            // Handle connection level and Pusher procotol errors
            ->retryWhen(function (Observable $errors) {
                return $errors->flatMap(function (Throwable $throwable) {
                    return $this->handleLowLevelError($throwable);
                });
            })

        // Share client
        ->share();
    }

    /**
     * @param  LoopInterface $loop
     * @param  string        $app      Application ID
     * @param  Resolver      $resolver Optional DNS resolver
     * @return AsyncClient
     */
    public static function create(LoopInterface $loop, string $app, Resolver $resolver = null): AsyncClient
    {
        // Rather not do this, but have to untill ReactPHP gets it's own global loop
        try {
            Scheduler::setAsyncFactory(function () use ($loop) {
                return new Scheduler\EventLoopScheduler($loop);
            });
        } catch (Throwable $t) {
        }

        try {
            Scheduler::setDefaultFactory(function () {
                return Scheduler::getImmediate();
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
     * Listen on a channel.
     *
     * @param  string     $channel Channel to listen on
     * @return Observable
     */
    public function channel(string $channel): Observable
    {
        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }

        // Ensure we only get messages for the given channel
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

            $this->subscribeOnChannel($channel);

            return new CallbackDisposable(function () use ($channel, $subscription) {
                // Send unsubscribe event
                $this->send(['event' => 'pusher:unsubscribe', 'data' => ['channel' => $channel]]);
                // Dispose our own subscription to messages
                $subscription->dispose();
                // Remove our channel from the channel list so we don't resubscribe in case we reconnect
                unset($this->channels[$channel]);
            });
        });

        // Share stream amount subscribers to this channel
        $this->channels[$channel] = $events->share();

        return $this->channels[$channel];
    }

    /**
     * Send a message through the client.
     *
     * @param array $message Message to send, will be json encoded
     *
     * @return A bool indicating whether or not the connection was active
     *           and the given message has been pass onto the connection.
     */
    public function send(array $message): bool
    {
        // Don't send messages when we aren't connected
        if ($this->sendSubject ===  null) {
            return false;
        }

        $this->sendSubject->onNext(json_encode($message));

        return true;
    }

    /**
     *  Handle errors as described at https://pusher.com/docs/pusher_protocol#error-codes.
     */
    private function handleLowLevelError(Throwable $throwable)
    {
        if (!($throwable instanceof WebsocketErrorException) &&
            !($throwable instanceof RuntimeException) &&
            !($throwable instanceof PusherErrorException)
        ) {
            return Observable::fromPromise(reject($throwable));
        }

        $code = $throwable->getCode();
        $pusherError = ($throwable instanceof WebsocketErrorException || $throwable instanceof PusherErrorException);

        // Errors 4000-4099, don't retry connecting
        if ($pusherError && $code >= 4000 && $code <= 4099) {
            return Observable::fromPromise(reject($throwable));
        }

        // Errors 4100-4199 reconnect after 1 or more seconds, we do it after 1.001 second
        if ($pusherError && $code >= 4100 && $code <= 4199) {
            return Observable::timer(1001);
        }

        // Errors 4200-4299 connection closed by Pusher, reconnect immediately, we wait 0.001 second
        if ($pusherError && $code >= 4200 && $code <= 4299) {
            return Observable::timer(1);
        }

        // Double our delay each time we get here
        $this->delay *= 2;

        return Observable::timer($this->delay);
    }

    /**
     * @param string $channel
     */
    private function subscribeOnChannel(string $channel)
    {
        $this->send(['event' => 'pusher:subscribe', 'data' => ['channel' => $channel]]);
    }

    /**
     * Get connection activity timeout from connection established event.
     *
     * @param Event $event
     */
    private function setActivityTimeout(Event $event)
    {
        $data = $event->getData();

        // No activity_timeout found on event
        if (!isset($data['activity_timeout'])) {
            return;
        }

        // activity_timeout holds zero or invalid value (we don't want to hammer Pusher)
        if ((int)$data['activity_timeout'] <= 0) {
            return;
        }

        $this->noActivityTimeout = (int)$data['activity_timeout'];
    }
}
