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
        $data = [];

        if (isset($message['data'])) {
            $data = $message['data'];
            if (!\is_array($message['data'])) {
                $data = \json_decode($message['data'], true);
            }
        }

        return new self(
            $message['event'],
            $data,
            $message['channel'] ?? ''
        );
    }

    public function jsonSerialize(): mixed
    {
        return \json_encode(['event' => $this->event, 'data' => $this->data, 'channel' => $this->channel]);
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

    public static function subscribeOn(string $channel, string $authKey = null, string $channelData = null): array
    {
        $data = ['channel' => $channel];

        if ($authKey) {
            $data['auth'] = $authKey;
        }

        if ($channelData) {
            $data['channel_data'] = $channelData;
        }

        return ['event' => 'pusher:subscribe', 'data' => $data];
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
