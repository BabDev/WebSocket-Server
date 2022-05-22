<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server middleware interface defines a middleware component for the WebSocket server.
 *
 * Server components should not directly implement this interface, components should implement
 * either {@see RawDataServerMiddleware} or {@see RequestAwareServerMiddleware} depending on where
 * in the middleware stack they should operate.
 */
interface ServerMiddleware extends Middleware
{
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
