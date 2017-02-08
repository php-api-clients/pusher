<?php declare(strict_types=1);

namespace ApiClients\Tests\Client\Pusher;

use ApiClients\Client\Pusher\Event;
use ApiClients\Tools\TestUtilities\TestCase;

final class EventTest extends TestCase
{
    public function eventsProvider()
    {
        yield [
            [
                'event' => 'event:name',
                'data' => json_encode([]),
            ],
            'event:name',
            '',
            [],
        ];

        yield [
            [
                'event' => 'event:name',
                'channel' => 'foo-bar',
                'data' => json_encode([]),
            ],
            'event:name',
            'foo-bar',
            [],
        ];

        $data = [
            'time' => time(),
            'pid' => getmypid(),
        ];
        yield [
            [
                'event' => 'event:name',
                'channel' => 'foo-bar',
                'data' => json_encode($data),
            ],
            'event:name',
            'foo-bar',
            $data,
        ];

    }

    /**
     * @param array $input
     * @param string $event
     * @param string $channel
     * @param array $data
     *
     * @dataProvider eventsProvider
     */
    public function testEvent(array $input, string $event, string $channel, array $data)
    {
        $eventObject = Event::createFromMessage($input);
        self::assertSame($event, $eventObject->getEvent());
        self::assertSame($channel, $eventObject->getChannel());
        self::assertSame($data, $eventObject->getData());
    }
}
