<?php

use ApiClients\Client\Pusher\AsyncClient;
use ApiClients\Client\Pusher\Event;
use React\EventLoop\Factory;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

$loop = Factory::create();

/**
 * The App ID isn't a secret and comes from a Pusher blog post:
 * @link https://blog.pusher.com/pusher-realtime-reddit-api/
 */
$client = AsyncClient::create($loop, require 'reddit.key.php');

$subReddits = \Rx\Observable::fromArray($argv)
    ->skip(1)
    ->flatMap(function ($subReddit) use ($client) {
        return $client->channel($subReddit);
    });

$subReddits->subscribe(
    function (Event $event) {
        echo 'Channel: ', $event->getChannel(), PHP_EOL;
        echo 'Event: ', $event->getEvent(), PHP_EOL;
        echo 'Data: ', json_encode($event->getData()), PHP_EOL;
    },
    function ($e) {
        echo (string)$e;
    },
    function () {
        echo 'Done!', PHP_EOL;
    }
);

$loop->run();
