<?php declare(strict_types=1);

use ApiClients\Client\Pusher\Client;
use ApiClients\Client\Pusher\Event;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

/**
 * The App ID isn't a secret and comes from a Pusher blog post:
 * @link https://blog.pusher.com/pusher-realtime-reddit-api/
 */
$client = new Client(require 'reddit.key.php');

$client->channel((string) $argv[1], function (Event $event) {
    echo 'Channel: ', $event->getChannel(), PHP_EOL;
    echo 'Event: ', $event->getEvent(), PHP_EOL;
    echo 'Data: ', json_encode($event->getData()), PHP_EOL;
});
