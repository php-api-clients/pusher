<?php

use ApiClients\Client\Pusher\Event;
use React\EventLoop\Factory;
use Rx\Observer\CallbackObserver;
use ApiClients\Client\Pusher\AsyncClient;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor/autoload.php';

$loop = Factory::create();

/**
 * The App ID isn't a secret and comes from the bitstamp docs:
 * @link https://www.bitstamp.net/websocket/
 */
$client = new AsyncClient($loop, 'de504dc5763aeef9ff52');

$channelitems = array('live_trades','live_trades_xrpusd');
foreach ($channelitems as $channelitem) {
    $client->channel($channelitem)->subscribe(new CallbackObserver(function (Event $event) {
        echo 'Channel: '. $event->getChannel(). PHP_EOL;
        echo 'Event: '. $event->getEvent(). PHP_EOL;
        echo 'Data: '. print_r($event->getData(),1). PHP_EOL;
    }));
}

$loop->run();
