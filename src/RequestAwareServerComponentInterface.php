<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use Psr\Http\Message\RequestInterface;

/**
 * The request aware server component interface defines a middleware component for the WebSocket server to be executed
 * after the HTTP request has been parsed.
 */
interface RequestAwareServerComponentInterface extends ServerComponentInterface
{
    /**
     * Handles a new connection to the server after parsing the HTTP request.
     */
    public function onOpen(ConnectionInterface $connection, RequestInterface $request): void;
}
