<?php

use ApiClients\Client\Pusher\Event;
use React\EventLoop\Factory;
use Rx\Observer\CallbackObserver;
use ApiClients\Client\Pusher\AsyncClient;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

$loop = Factory::create();

/**
 * The App ID isn't a secret and comes from a Pusher blog post:
 * @link https://blog.pusher.com/pusher-realtime-reddit-api/
 */
$client = new AsyncClient($loop, require 'reddit.key.php');

$subReddits = $argv;
array_shift($subReddits);
foreach ($subReddits as $subReddit) {
    $client->channel($subReddit)->subscribe(new CallbackObserver(function (Event $event) {
        echo 'Channel: ', $event->getChannel(), PHP_EOL;
        echo 'Event: ', $event->getEvent(), PHP_EOL;
        echo 'Data: ', json_encode($event->getData()), PHP_EOL;
    }));
}

$loop->run();
