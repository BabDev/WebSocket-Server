<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server component interface defines a middleware component for the WebSocket server which handles the raw incoming
 * data.
 *
 * These middleware components are intended for early execution and are responsible for generating the request for
 * {@see RequestAwareServerMiddleware} middleware to parse
 */
interface RawDataServerMiddleware extends ServerMiddleware
{
    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void;
}
