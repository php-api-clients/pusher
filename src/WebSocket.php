<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use PackageVersions\Versions;
use React\Dns\Resolver\Resolver;
use React\EventLoop\LoopInterface;
use React\Socket\Connector;
use Rx\DisposableInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Subject\ReplaySubject;
use Rx\Subject\Subject;
use Rx\Websocket\Client;

/**
 * Class WebSocket - WebSocket wrapper that queues messages while the connection is being established.
 */
final class WebSocket extends Subject
{
    private $ws;
    private $sendSubject;

    /**
     * @internal
     * @param  Observable                $ws
     * @throws \InvalidArgumentException
     */
    public function __construct(Observable $ws)
    {
        $this->sendSubject = new ReplaySubject();
        $this->ws = $ws;
    }

    public static function createFactory(string $url, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null, Resolver $resolver = null): Subject
    {
        if (Versions::getVersion('rx/websocket')[0] === '1' || $resolver === null) {
            return new self(
                new Client($url, $useMessageObject, $subProtocols, $loop, $resolver)
            );
        }

        return new self(
            new Client($url, $useMessageObject, $subProtocols, $loop, new Connector(
                $loop,
                [
                    'dns' => $resolver,
                ]
            ))
        );
    }

    public function onNext($value): void
    {
        $this->sendSubject->onNext($value);
    }

    protected function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        return $this->ws
            ->do(function (Subject $ms): void {
                // Replay buffered messages onto the MessageSubject
                $this->sendSubject->subscribe($ms);

                // Now that the connection has been established, use the message subject directly.
                $this->sendSubject = $ms;
            })
            ->finally(function (): void {
                // The connection has closed, so start buffering messages util it reconnects.
                $this->sendSubject = new ReplaySubject();
            })
            ->switch()
            ->repeatDelay()
            ->subscribe($observer);
    }
}
