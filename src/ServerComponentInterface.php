<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server component interface defines a middleware component for the WebSocket server.
 */
interface ServerComponentInterface
{
    /**
     * Handles a new connection to the server.
     */
    public function onOpen(ConnectionInterface $connection): void;

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(ConnectionInterface $connection, string $data): void;

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(ConnectionInterface $connection): void;

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(ConnectionInterface $connection, \Throwable $e): void;
}
