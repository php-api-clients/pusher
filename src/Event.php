<?php declare(strict_types=1);

namespace ApiClients\Client\Pusher;

final class Event
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

    public static function createFromMessage(array $message): self
    {
        return new self(
            $message['event'],
            json_decode($message['data'], true),
            isset($message['channel']) ? $message['channel'] : ''
        );
    }

    /**
     * @param string $event
     * @param array $data
     * @param string $channel
     */
    public function __construct(string $event, array $data, string $channel = '')
    {
        $this->event = $event;
        $this->data = $data;
        $this->channel = $channel;
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
}
