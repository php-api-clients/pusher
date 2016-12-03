<?php declare(strict_types=1);

use ApiClients\Client\Pusher\Client;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

/**
 * The App ID isn't a secret and comes from a Pusher blog post:
 * @link https://blog.pusher.com/pusher-realtime-reddit-api/
 */
$client = new Client(require 'reddit.key.php');

$client->channel((string) $argv[1], function ($event) {
    echo 'Channel: ', $event->channel, PHP_EOL;
    echo 'Event: ', $event->event, PHP_EOL;
    echo 'Data: ', $event->data, PHP_EOL;
});
