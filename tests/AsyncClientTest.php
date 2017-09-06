<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\Event;
use React\Dns\Resolver\Resolver;
use React\EventLoop\Factory;
use RuntimeException;
use Rx\Exception\TimeoutException;
use Rx\Observable;
use function React\Promise\reject;

final class AsyncClientTest extends TestCase
{
    public function testCreateFactory()
    {
        $loop = Factory::create();
        $appId = uniqid('app-id-', true);
        self::assertInstanceOf(AsyncClient::class, AsyncClient::create($loop, $appId));
    }

    public function testConnectionError()
    {
        $capturedException = null;
        $error = new RuntimeException();
        $observable = Observable::error($error);
        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);
        $client = new AsyncClient($webSocket);
        $client->channel('test')->subscribe(
            function () {
            },
            function ($e) use (&$capturedException) {
                $capturedException = $e;
            }
        );
        self::assertNotNull($capturedException);
    }

    public function testConnectionRetry()
    {
        $loop = Factory::create();
        $error = new RuntimeException('', 4199);
        $resolver = $this->prophesize(Resolver::class);
        $resolver->resolve('ws.pusherapp.com')->shouldBeCalled()->willReturn(reject($error));
        $client = AsyncClient::create($loop, 'abc', $resolver->reveal());
        $client->channel('test')->subscribe(null, function ($e) {
        });
        $loop->addTimer(1, function () {
        });
        $loop->tick();
    }

    public function testWebSocketNever()
    {
        $webSocket = new TestWebSocketSubject(Observable::never(), $this->scheduler);

        $results = $this->scheduler->startWithCreate(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        });

        $this->assertMessages([], $results->getMessages());
    }

    public function testWebSocketEmpty()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onCompleted(235),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithCreate(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        });

        $this->assertMessages([
            onCompleted(237),
        ], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 235)], $observable->getSubscriptions());
    }

    public function testWebSocketDispose()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onCompleted(435),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithDispose(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        }, 300);

        $this->assertMessages([], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 300)], $observable->getSubscriptions());
    }

    public function testPusherConnection()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":120}"}'),
            onCompleted(635),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithCreate(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        });

        $this->assertMessages([onCompleted(637)], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 635)], $observable->getSubscriptions());

        $this->assertEquals([
            [320, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [637, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }

    public function testPusherSubscribed()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":120}"}'),
            onNext(340, '{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"test"}'),
            onCompleted(635),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithCreate(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        });

        $this->assertMessages([onCompleted(637)], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 635)], $observable->getSubscriptions());

        $this->assertEquals([
            [320, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [637, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }

    public function testPusherData()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":120}"}'),
            onNext(340, '{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"test"}'),
            onNext(350, '{"event":"new-listing","data":["test1"],"channel":"test"}'),
            onNext(370, '{"event":"new-listing","data":["test10"],"channel":"other"}'),
            onNext(390, '{"event":"new-listing","data":["test2"],"channel":"test"}'),
            onNext(400, '{"event":"new-listing","data":["test3"],"channel":"test"}'),
            onCompleted(900),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithCreate(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        });

        $this->assertMessages([
            onNext(350, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test1"],"channel":"test"}', true))),
            onNext(390, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test2"],"channel":"test"}', true))),
            onNext(400, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test3"],"channel":"test"}', true))),
            onCompleted(902),
        ], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 900)], $observable->getSubscriptions());

        $this->assertEquals([
            [320, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [902, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }

    public function testPusherDataSameChannel()
    {
        $observable = $this->createColdObservable([
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":120}"}'),
            onNext(340, '{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"test"}'),
            onNext(350, '{"event":"new-listing","data":["test1"],"channel":"test"}'),
            onNext(370, '{"event":"new-listing","data":["test10"],"channel":"other"}'),
            onNext(390, '{"event":"new-listing","data":["test2"],"channel":"test"}'),
            onNext(400, '{"event":"new-listing","data":["test3"],"channel":"test"}'),
            onCompleted(900),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $client = new AsyncClient($webSocket);

        $results1 = $this->scheduler->createObserver();
        $results2 = $this->scheduler->createObserver();

        $this->scheduler->scheduleAbsolute($this->scheduler::CREATED, function () use ($client, $results1) {
            $client->channel('test')->subscribe($results1);
        });

        $this->scheduler->scheduleAbsolute(460, function () use ($client, $results2) {
            $client->channel('test')->subscribe($results2);
        });

        $this->scheduler->start();

        $this->assertMessages([
            onNext(450, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test1"],"channel":"test"}', true))),
            onNext(490, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test2"],"channel":"test"}', true))),
            onNext(500, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test3"],"channel":"test"}', true))),
            onCompleted(1002),
        ], $results1->getMessages());

        $this->assertMessages([
            onNext(490, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test2"],"channel":"test"}', true))),
            onNext(500, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test3"],"channel":"test"}', true))),
            onCompleted(1002),
        ], $results2->getMessages());

        $this->assertSubscriptions([subscribe(100, 1000)], $observable->getSubscriptions());

        $this->assertEquals([
            [420, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [1002, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }

    public function testPusherPing()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":1}"}'),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithDispose(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        }, 1600);

        $this->assertMessages([], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 1600)], $observable->getSubscriptions());

        $this->assertEquals([
            [320, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [1321, '{"event":"pusher:ping"}'],
            [1600, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }

    public function testPusherTimeout()
    {
        $observable = $this->createHotObservable([
            onNext(150, 1),
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":1}"}'),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithDispose(function () use ($webSocket) {
            return (new AsyncClient($webSocket))->channel('test');
        }, 5000);

        $this->assertMessages([onError(2322, new TimeoutException())], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 2322)], $observable->getSubscriptions());

        $this->assertEquals([
            [320, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [1321, '{"event":"pusher:ping"}'],
            [2322, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }

    public function testPusherReconnectInnerError()
    {
        $observable = $this->createColdObservable([
            onNext(320, '{"event":"pusher:connection_established","data":"{\"socket_id\":\"218656.9503498\",\"activity_timeout\":120}"}'),
            onNext(340, '{"event":"pusher_internal:subscription_succeeded","data":"{}","channel":"test"}'),
            onNext(350, '{"event":"new-listing","data":["test1"],"channel":"test"}'),
            onError(370, new \Exception()),
        ]);

        $webSocket = new TestWebSocketSubject($observable, $this->scheduler);

        $results = $this->scheduler->startWithDispose(function () use ($webSocket) {
            return (new AsyncClient($webSocket))
                ->channel('test')
                ->retryWhen(function ($e) {
                    return $e->take(1)->delay(100);
                });
        }, 5000);

        $this->assertMessages([
            onNext(550, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test1"],"channel":"test"}', true))),
            onNext(1020, Event::createFromMessage(json_decode('{"event":"new-listing","data":["test1"],"channel":"test"}', true))),
            onCompleted(1040),
        ], $results->getMessages());

        $this->assertSubscriptions([subscribe(200, 570), subscribe(670, 1040)], $observable->getSubscriptions());

        $this->assertEquals([
            [520, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [570, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
            [990, '{"event":"pusher:subscribe","data":{"channel":"test"}}'],
            [1040, '{"event":"pusher:unsubscribe","data":{"channel":"test"}}'],
        ], $webSocket->getSentMessages());
    }
}
