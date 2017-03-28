<?php

use React\EventLoop\Factory;
use function EventLoop\setLoop;

require dirname(__DIR__) . DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR . 'autoload.php';

setLoop(Factory::create());
