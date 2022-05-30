<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The websocket server middleware interface defines a server middleware component which exposes the sub-protocols supported
 * by the server.
 */
interface WebSocketServerMiddleware extends ServerMiddleware
{
    /**
     * @return list<string>
     */
    public function getSubProtocols(): array;
}
