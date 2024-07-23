<?php

require_once './vendor/autoload.php';

use PhpServer\Infrastructure\Server\Server;
use PhpServer\Services\DependencyInjector\ServiceContainer;

$container = new ServiceContainer();
$server = $container->get(Server::class);
$server->listen();
