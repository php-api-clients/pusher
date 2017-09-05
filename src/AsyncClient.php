<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use RuntimeException;
use Rx\Observable;
use Rx\Scheduler;
use Rx\Subject\Subject;
use Rx\Websocket\WebsocketErrorException;
use Throwable;

final class AsyncClient
{
    const DEFAULT_DELAY = 200;
    const NO_ACTIVITY_TIMEOUT = 120;
    const NO_PING_RESPONSE_TIMEOUT = 30;

    /**
     * @var Observable
     */
    private $messages;

    /**
     * @var array
     */
    private $channels = [];

    /**
     * @var int
     */
    private $delay = self::DEFAULT_DELAY;

    /**
     * @var Subject
     */
    private $client;

    /**
     * @var Observable
     */
    private $connected;

    /**
     * @internal
     * @param  Subject                   $client
     * @throws \InvalidArgumentException
     */
    public function __construct(Subject $client)
    {
        $this->client = $client;

        /** @var Observable $events */
        $events = $client
            ->_ApiClients_jsonDecode()
            ->map([Event::class, 'createFromMessage'])
            ->singleInstance();

        $pusherErrors = $events
            ->filter([Event::class, 'isError'])
            ->flatMap(function (Event $event) {
                $throwable = new PusherErrorException($event->getData()['message'], (int)$event->getData()['code']);

                return Observable::error($throwable);
            });

        $this->connected = $events
            ->filter([Event::class, 'connectionEstablished'])
            ->singleInstance();

        $this->messages = $events
            ->merge($this->timeout($events))
            ->merge($pusherErrors)
            ->retryWhen(function (Observable $errors) {
                return $errors->flatMap(function (Throwable $throwable) {
                    return $this->handleLowLevelError($throwable);
                });
            })
            ->singleInstance();
    }

    /**
     * @param  LoopInterface             $loop
     * @param  string                    $app      Application ID
     * @param  Resolver                  $resolver Optional DNS resolver
     * @throws \InvalidArgumentException
     * @return AsyncClient
     */
    public static function create(LoopInterface $loop, string $app, Resolver $resolver = null): AsyncClient
    {
        try {
            Scheduler::setDefaultFactory(function () use ($loop) {
                return new Scheduler\EventLoopScheduler($loop);
            });
        } catch (Throwable $t) {
        }

        return new self(
            new Websocket(ApiSettings::createUrl($app), false, [], $loop, $resolver)
        );
    }

    /**
     * Listen on a channel.
     *
     * @param  string                    $channel Channel to listen on
     * @throws \InvalidArgumentException
     * @return Observable
     */
    public function channel(string $channel): Observable
    {
        // Only join a channel once
        if (isset($this->channels[$channel])) {
            return $this->channels[$channel];
        }

        // Ensure we only get messages for the given channel
        $channelMessages = $this->messages->filter(function (Event $event) use ($channel) {
            return $event->getChannel() !== '' && $event->getChannel() === $channel;
        });

        $subscribe = $this->connected
            ->do(function () use ($channel) {
                // Subscribe to pusher channel after connected
                $this->send(Event::subscribeOn($channel));
            })
            ->flatMapTo(Observable::empty());

        // Observable representing channel events
        $events = $channelMessages
            ->merge($subscribe)
            ->filter([Event::class, 'subscriptionSucceeded'])
            ->finally(function () use ($channel) {
                // Send unsubscribe event
                $this->send(Event::unsubscribeOn($channel));

                // Remove our channel from the channel list so we don't resubscribe in case we reconnect
                unset($this->channels[$channel]);
            });

        $this->channels[$channel] = $events;

        return $this->channels[$channel];
    }

    /**
     * Send a message through the client.
     *
     * @param array $message Message to send, will be json encoded
     *
     */
    public function send(array $message)
    {
        $this->client->onNext(json_encode($message));
    }

    /**
     * Returns an observable of TimeoutException.
     * The timeout observable will get cancelled every time a new event is received.
     *
     * @param  Observable $events
     * @return Observable
     */
    public function timeout(Observable $events): Observable
    {
        $timeoutDuration = $this->connected->map(function (Event $event) {
            return ($event->getData()['activity_timeout'] ?? self::NO_ACTIVITY_TIMEOUT) * 1000;
        });

        return $timeoutDuration
            ->combineLatest([$events])
            ->pluck(0)
            ->concat(Observable::of(-1))
            ->flatMapLatest(function (int $time) {

                // If the events observable ends, return an empty observable so we don't keep the stream alive
                if ($time === -1) {
                    return Observable::empty();
                }

                return Observable::never()
                    ->timeout($time)
                    ->catch(function () use ($time) {
                        // ping (do something that causes incoming stream to get a message)
                        $this->send(Event::ping());
                        // this timeout will actually timeout with a TimeoutException - causing
                        // everything above this to dispose
                        return Observable::never()->timeout($time);
                    });
            });
    }

    /**
     * Handle errors as described at https://pusher.com/docs/pusher_protocol#error-codes.
     * @param  Throwable  $throwable
     * @return Observable
     */
    private function handleLowLevelError(Throwable $throwable): Observable
    {
        // Only allow certain, relevant, exceptions
        if (!($throwable instanceof WebsocketErrorException) &&
            !($throwable instanceof RuntimeException) &&
            !($throwable instanceof PusherErrorException)
        ) {
            return Observable::error($throwable);
        }

        $code = $throwable->getCode();
        $pusherError = ($throwable instanceof WebsocketErrorException || $throwable instanceof PusherErrorException);

        // Errors 4000-4099, don't retry connecting
        if ($pusherError && $code >= 4000 && $code <= 4099) {
            return Observable::error($throwable);
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
}
