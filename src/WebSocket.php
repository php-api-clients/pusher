<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

use React\EventLoop\LoopInterface;
use React\Dns\Resolver\Resolver;
use Rx\Subject\ReplaySubject;
use Rx\DisposableInterface;
use Rx\ObserverInterface;
use Rx\Websocket\Client;
use Rx\Subject\Subject;

/**
 * Class WebSocket - WebSocket wrapper that queues messages while the connection is being established.
 */
final class WebSocket extends Subject
{
    private $ws;
    private $sendSubject;

    public function __construct(string $url, bool $useMessageObject = false, array $subProtocols = [], LoopInterface $loop = null, Resolver $dnsResolver = null)
    {
        $this->sendSubject = new ReplaySubject();
        $this->ws = new Client($url, $useMessageObject, $subProtocols, $loop, $dnsResolver);
    }

    protected function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        return $this->ws
            ->do(function ($ms) {
                // Replay buffered messages onto the MessageSubject
                $this->sendSubject->subscribe($ms);

                // Now that the connection has been established, use the message subject directly.
                $this->sendSubject = $ms;
            })
            ->finally(function () {
                // The connection has closed, so start buffering messages util it reconnects.
                $this->sendSubject = new ReplaySubject();
            })
            ->mergeAll()
            ->subscribe($observer);
    }

    public function onNext($value)
    {
        $this->sendSubject->onNext($value);
    }
}
