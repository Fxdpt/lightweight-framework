<?php

namespace PhpServer\Infrastructure\Server;

use PhpServer\Infrastructure\ServerRequest;
use Socket;
use Throwable;

final class Server
{
    private Socket $socket;

    /**
     * @param int $port
     */
    public function __construct(private readonly int $port = 8080)
    {
        $this->createSocket();
        $this->bindSocket();
    }

    private function createSocket(): void
    {
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)
            ?: throw new \Exception("Unable to create socket");
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

    }

    private function bindSocket(): void
    {
        socket_bind($this->socket, "localhost", $this->port)
            ?: throw new \Exception("Unable to bind socket");
    }

    public function listen(): void
    {
        while (true) {
            socket_listen($this->socket);

            if (! $client = socket_accept($this->socket)) {
                continue;
            }

            try {
                $request = Request::withFullParts(socket_read($client, 2048));
                $response = Response::buildResponse(200, ['Content-Type' => "application/json"], '{"test":"test"}');
                socket_write($client, $response, strlen($response));
            } catch (Throwable $ex) {
                error_log($ex);
                socket_close($client);
                continue;
            }

            socket_close($client);
        }
    }
}
