<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use ApiClients\Client\Pusher\WebSocket;
use Rx\Subject\Subject;
use Rx\Testing\MockObserver;

final class WebSocketTest extends TestCase
{
    public function testWebSocketNever()
    {
        $ws = $this->createHotObservable([
            onNext(150, 1),
        ]);

        $results = $this->scheduler->startWithCreate(function () use ($ws) {
            return new WebSocket($ws);
        });

        $this->assertMessages([], $results->getMessages());
    }

    public function testWebSocketEmpty()
    {
        $ws = $this->createHotObservable([
            onNext(150, 1),
            onCompleted(235),
        ]);

        $results = $this->scheduler->startWithCreate(function () use ($ws) {
            return new WebSocket($ws);
        });

        $this->assertMessages([], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 235)], $ws->getSubscriptions());
    }

    public function testWebSocketDispose()
    {
        $ws = $this->createHotObservable([
            onNext(150, 1),
            onCompleted(435),
        ]);

        $results = $this->scheduler->startWithDispose(function () use ($ws) {
            return new WebSocket($ws);
        }, 300);

        $this->assertMessages([], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 300)], $ws->getSubscriptions());
    }

    public function testWebSocketReconnect()
    {
        $ws = $this->createHotObservable([
            onNext(150, 1),
            onCompleted(435),
        ]);

        $results = $this->scheduler->startWithDispose(function () use ($ws) {
            return new WebSocket($ws);
        }, 2000);

        $this->assertMessages([], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 435), subscribe(1935, 2000)], $ws->getSubscriptions());
    }

    public function testWebSocketSingleValue()
    {
        $messageSubject = new Subject();

        $ws = $this->createHotObservable([
            onNext(150, 1),
            onNext(201, $messageSubject),
            onCompleted(435),
        ]);

        $this->scheduler->scheduleAbsolute(230, function () use ($messageSubject) {
            $messageSubject->onNext(2);
        });

        $results = $this->scheduler->startWithCreate(function () use ($ws) {
            return new WebSocket($ws);
        });

        $this->assertMessages([onNext(230, 2)], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 435)], $ws->getSubscriptions());

        $this->assertFalse($messageSubject->hasObservers());
    }

    public function testWebSocketMultipleValues()
    {
        $messageSubject = new Subject();

        $ws = $this->createHotObservable([
            onNext(150, 1),
            onNext(201, $messageSubject),
            onCompleted(435),
        ]);

        $this->scheduler->scheduleAbsolute(230, function () use ($messageSubject) {
            $messageSubject->onNext(2);
        });

        $this->scheduler->scheduleAbsolute(235, function () use ($messageSubject) {
            $messageSubject->onNext(3);
        });

        $this->scheduler->scheduleAbsolute(240, function () use ($messageSubject) {
            $messageSubject->onNext(4);
        });

        $results = $this->scheduler->startWithCreate(function () use ($ws) {
            return new WebSocket($ws);
        });

        $this->assertMessages([
            onNext(230, 2),
            onNext(235, 3),
            onNext(240, 4),
        ], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 435)], $ws->getSubscriptions());

        $this->assertFalse($messageSubject->hasObservers());
    }

    public function testWebSocketMultipleValuesReconnect()
    {
        $messageSubject = new Subject();

        $ws = $this->createHotObservable([
            onNext(150, 1),
            onNext(201, $messageSubject),
            onCompleted(435),
        ]);

        $this->scheduler->scheduleAbsolute(230, function () use ($messageSubject) {
            $messageSubject->onNext(2);
        });

        $this->scheduler->scheduleAbsolute(1945, function () use ($messageSubject) {
            $messageSubject->onNext(3);
        });

        $this->scheduler->scheduleAbsolute(3695, function () use ($messageSubject) {
            $messageSubject->onNext(4);
        });

        $results = $this->scheduler->startWithDispose(function () use ($ws) {
            return new WebSocket($ws);
        }, 4000);

        $this->assertMessages([
            onNext(230, 2),
            onNext(1945, 3),
            onNext(3695, 4),
        ], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 435)], $ws->getSubscriptions());

        $this->assertFalse($messageSubject->hasObservers());
    }

    public function testWebSocketOnNextBeforeConnected()
    {
        $messageSubject = new Subject();

        $ws = $this->createHotObservable([
            onNext(205, $messageSubject), //Connected
            onCompleted(435),
        ]);

        $websocket = new WebSocket($ws);

        $websocket->subscribe();

        $results = new MockObserver($this->scheduler);

        $messageSubject->subscribe($results);

        $this->scheduler->scheduleAbsolute(200, function () use ($websocket) {
            $websocket->onNext('test');
        });

        $this->scheduler->start();

        $this->assertMessages([
            onNext(206, 'test'),
        ], $results->getMessages());
    }

    public function testWebSocketOnNextAfterConnected()
    {
        $messageSubject = new Subject();

        $ws = $this->createHotObservable([
            onNext(205, $messageSubject), //Connected
            onCompleted(435),
        ]);

        $websocket = new WebSocket($ws);

        $websocket->subscribe();

        $results = new MockObserver($this->scheduler);

        $messageSubject->subscribe($results);

        $this->scheduler->scheduleAbsolute(210, function () use ($websocket) {
            $websocket->onNext('test');
        });

        $this->scheduler->start();

        $this->assertMessages([
            onNext(210, 'test'),
        ], $results->getMessages());
    }
}
