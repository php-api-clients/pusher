<?php

namespace ApiClients\Tests\Client\Pusher;

use Rx\DisposableInterface;
use Rx\Observable;
use Rx\ObserverInterface;
use Rx\Subject\Subject;
use Rx\Testing\TestScheduler;

class TestWebSocketSubject extends Subject
{
    private $observable;
    private $sentMessages = [];
    private $scheduler;

    public function __construct(Observable $observable, TestScheduler $scheduler)
    {
        $this->observable = $observable;
        $this->scheduler = $scheduler;
    }

    protected function _subscribe(ObserverInterface $observer): DisposableInterface
    {
        return $this->observable->subscribe($observer);
    }

    public function getSentMessages(): array
    {
        return $this->sentMessages;
    }

    public function onNext($value)
    {
        $this->sentMessages[] = [$this->scheduler->getClock(), $value];
    }
}
