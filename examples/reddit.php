<?php

use React\EventLoop\Factory;
use function Ratchet\Client\connect;
use Rx\Observer\CallbackObserver;
use WyriHaximus\Pusher\AsyncClient;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

$display = function ($event) {
    echo 'Channel: ', $event['channel'], PHP_EOL;
    echo 'Event: ', $event['event'], PHP_EOL;
    echo 'Data: ', $event['data'], PHP_EOL;
};

$loop = Factory::create();

/**
 * @link https://blog.pusher.com/pusher-realtime-reddit-api/
 */
$client = new AsyncClient($loop, '50ed18dd967b455393ed');

$subReddits = $argv;
array_shift($subReddits);
foreach ($subReddits as $subReddit) {
    $client->subscribe($subReddit)->subscribe(new CallbackObserver($display));
}

$loop->run();
