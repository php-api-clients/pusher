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
    //const NO_ACTIVITY_TIMEOUT = 12;
    //const NO_PING_RESPONSE_TIMEOUT = 3;

    /**
     * @var LoopInterface
     */
    protected $loop;

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
        $this->messages = $client->shareReplay(1)
            // Save this subject for sending stuff
            ->do(function (MessageSubject $ms) {
                echo 'set snedSubject', PHP_EOL;
                $this->sendSubject = $ms;
            })

            // Make sure if there is a disconnect or something
            // that we unset the sendSubject
            ->finally(function () {
                echo 'unset snedSubject', PHP_EOL;
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
                    ->timeout(self::NO_ACTIVITY_TIMEOUT * 1000)
                    ->catch(function () use ($x) {
                        echo 'send ping', PHP_EOL;
                        // ping (do something that causes incoming stream to get a message)
                        $this->send(['event' => 'pusher:ping']);
                        // this timeout will actually timeout with a TimeoutException - causing
                        //   everything above this to dispose
                        return Observable::never()->timeout(self::NO_PING_RESPONSE_TIMEOUT * 1000);
                    })
                    ->startWith($x);
            })
            ->retryWhen(function (Observable $errors) {
                echo __LINE__, ': ', time(), PHP_EOL;
                return $errors->flatMap(function (Throwable $throwable) {
                    return $this->handleLowLevelError($throwable);
                });
            })
            ->_ApiClients_jsonDecode()
            ->map(function (array $message) {
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
        if ($this->sendSubject ===  null) {
            echo 'send subject is null when trying to send', PHP_EOL;
            return;
        }

        echo __LINE__, ' Sending JSON: ', json_encode($message), PHP_EOL;
        $this->sendSubject->onNext(json_encode($message));
    }

    private function handleLowLevelError(Throwable $throwable)
    {
        $this->delay *= 2;
        echo get_class($throwable), PHP_EOL;
        /*echo get_class($throwable->getPrevious()), PHP_EOL;
        echo get_class($throwable->getPrevious()->getPrevious()), PHP_EOL;
        echo get_class($throwable->getPrevious()->getPrevious()->getPrevious()), PHP_EOL;*/
        echo __LINE__, ': ', time(), PHP_EOL;
        return Observable::timer($this->delay);
    }
}
