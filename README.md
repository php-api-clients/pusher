# Pusher API Client for PHP 7 and 8

[![Linux Build Status](https://travis-ci.org/php-api-clients/pusher.svg?branch=master)](https://travis-ci.org/php-api-clients/pusher)
[![Latest Stable Version](https://poser.pugx.org/api-clients/pusher/v/stable.png)](https://packagist.org/packages/api-clients/pusher)
[![Total Downloads](https://poser.pugx.org/api-clients/pusher/downloads.png)](https://packagist.org/packages/api-clients/pusher)
[![Code Coverage](https://scrutinizer-ci.com/g/php-api-clients/pusher/badges/coverage.png?b=master)](https://scrutinizer-ci.com/g/php-api-clients/pusher/?branch=master)
[![License](https://poser.pugx.org/api-clients/pusher/license.png)](https://packagist.org/packages/api-clients/pusher)
[![PHP 7 ready](http://php7ready.timesplinter.ch/php-api-clients/pusher/badge.svg)](https://appveyor-ci.org/php-api-clients/pusher)

# Installation

To install via [Composer](http://getcomposer.org/), use the command below, it will automatically detect the latest version and bind it with `^`.

```bash
composer require api-clients/pusher
```

# Usage

```php
$loop = Factory::create();
$client = AsyncClient::create($loop, 'Application ID here');
// OR when you need to specify the cluster
$client = AsyncClient::create($loop, 'Application ID here', null, 'cluster-here');

$client->channel('channel_name')->subscribe(
    function (Event $event) { // Gets called for each incoming event
        echo 'Channel: ', $event->getChannel(), PHP_EOL;
        echo 'Event: ', $event->getEvent(), PHP_EOL;
        echo 'Data: ', json_encode($event->getData()), PHP_EOL;
    },
    function ($e) { // Gets called on errors
        echo (string)$e;
    },
    function () { // Gets called when the end of the stream is reached
        echo 'Done!', PHP_EOL;
    }
);

$loop->run();
```

For more examples see the [examples directory](examples).

# Features

This project aims to be 100% compatible with [Pusher's features](https://pusher.com/features) in `v1.3`.

- [X] Subscribe to channels
- [x] Presence channels
- [x] Authentication

# License

The MIT License (MIT)

Copyright (c) 2017 Cees-Jan Kiewiet

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
