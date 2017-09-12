<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

final class Event implements \JsonSerializable
{
    /**
     * @var string
     */
    private $event;

    /**
     * @var string
     */
    private $channel;

    /**
     * @var array
     */
    private $data;

    /**
     * @param string $event
     * @param array  $data
     * @param string $channel
     */
    public function __construct(string $event, array $data, string $channel = '')
    {
        $this->event = $event;
        $this->data = $data;
        $this->channel = $channel;
    }

    public static function createFromMessage(array $message): self
    {
        return new self(
            $message['event'],
            is_array($message['data']) ? $message['data'] : json_decode($message['data'], true),
            isset($message['channel']) ? $message['channel'] : ''
        );
    }

    public function jsonSerialize()
    {
        return json_encode(['event' => $this->event, 'data' => $this->data, 'channel' => $this->channel]);
    }

    /**
     * @return string
     */
    public function getEvent()
    {
        return $this->event;
    }

    /**
     * @return string
     */
    public function getChannel()
    {
        return $this->channel;
    }

    /**
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    public static function isError(Event $event): bool
    {
        return $event->getEvent() === 'pusher:error';
    }

    public static function subscriptionSucceeded(Event $event): bool
    {
        return $event->getEvent() !== 'pusher_internal:subscription_succeeded';
    }

    public static function connectionEstablished(Event $event): bool
    {
        return $event->getEvent() === 'pusher:connection_established';
    }

    public static function subscribeOn(string $channel): array
    {
        return ['event' => 'pusher:subscribe', 'data' => ['channel' => $channel]];
    }

    public static function unsubscribeOn(string $channel): array
    {
        return ['event' => 'pusher:unsubscribe', 'data' => ['channel' => $channel]];
    }

    public static function ping(): array
    {
        return ['event' => 'pusher:ping'];
    }
}
