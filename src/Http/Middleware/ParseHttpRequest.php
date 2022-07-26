<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Http\GuzzleRequestParser;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\ServerMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * The parse HTTP request server middleware transforms the incoming HTTP request into a {@see RequestInterface} object.
 */
final class ParseHttpRequest implements ServerMiddleware
{
    use ClosesConnectionWithResponse;

    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly RequestParser $requestParser = new GuzzleRequestParser(),
    ) {
    }

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void
    {
        $connection->getAttributeStore()->set('http.headers_received', false);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            $this->middleware->onMessage($connection, $data);

            return;
        }

        try {
            if (null === ($request = $this->requestParser->parse($connection, $data))) {
                return;
            }
        } catch (\OverflowException) {
            $this->close($connection, 413);

            return;
        }

        $connection->getAttributeStore()->set('http.headers_received', true);
        $connection->getAttributeStore()->set('http.request', $request);

        $this->middleware->onOpen($connection);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            $this->middleware->onClose($connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            $this->middleware->onError($connection, $throwable);
        } else {
            $this->close($connection, 500);
        }
    }
}
