<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server middleware interface defines a middleware component for the WebSocket server.
 */
interface ServerMiddleware extends Middleware
{
    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void;

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void;

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void;

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void;
}
