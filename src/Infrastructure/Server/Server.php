<?php

namespace PhpServer\Infrastructure\Server;

use PhpServer\Infrastructure\ServerRequest;
use PhpServer\Services\DependencyInjector\ServiceContainer;
use Socket;
use Throwable;

final class Server
{
    private Socket $socket;

    /**
     * @param Request $request
     * @param int $port
     */
    public function __construct(private readonly Request $request, private readonly int $port = 8080)
    {
        // Initialize the server by creating and binding a socket
        $this->createSocket();
        $this->bindSocket();
    }

    /**
     * Create a socket for the server
     *
     * @return void
     */
    private function createSocket(): void
    {
        // Create a socket with specific parameters
        $this->socket = socket_create(AF_INET, SOCK_STREAM, SOL_TCP)
            ?: throw new \Exception("Unable to create socket");
        socket_set_option($this->socket, SOL_SOCKET, SO_REUSEADDR, 1);

    }

    /**
     * Bind the socket to a specific address and port
     *
     * @return void
     */
    private function bindSocket(): void
    {
        // Bind the socket to the localhost and specified port
        socket_bind($this->socket, "localhost", $this->port)
            ?: throw new \Exception("Unable to bind socket");
    }

    /**
     * Start listening for incoming client connections
     *
     * @return void
     */
    public function listen(): void
    {
        // Continuously listen for incoming client connections
        while (true) {
            socket_listen($this->socket);

            if (! $client = socket_accept($this->socket)) {
                continue;
            }

            try {
                $builtRequest = $this->request->withFullParts(socket_read($client, 2048));

                $response = Response::buildResponse(200, ['Content-Type' => "application/json"], '{"test":"test"}');
                socket_write($client, $response, strlen($response));
            } catch (Throwable $ex) {
                var_dump($ex);
                error_log($ex);
                socket_close($client);
                continue;
            }

            socket_close($client);
        }
    }
}
