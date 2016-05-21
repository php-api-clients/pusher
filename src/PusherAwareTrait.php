<?php
declare(strict_types=1);

namespace WyriHaximus\Pusher;

trait TransportAwareTrait
{
    private $pusher;

    public function setPusher(AsyncClient $pusher)
    {
        $this->pusher = $pusher;
    }

    protected function getTransport(): AsyncClient
    {
        return $this->pusher;
    }
}
