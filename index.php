<?php

require_once './vendor/autoload.php';

use PhpServer\Infrastructure\Server\Server;
use PhpServer\Infrastructure\Server\Validator\ApplicationJsonValidator;
use PhpServer\Infrastructure\Server\Validator\FormUrlEncodedValidator;

$validators = [
    new ApplicationJsonValidator(),
    new FormUrlEncodedValidator()
];
$server = new Server();
$server->listen();
