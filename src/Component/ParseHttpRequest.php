<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Component;

use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\ConnectionInterface;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\Http\RequestParserInterface;
use BabDev\WebSocket\Server\RawDataServerComponentInterface;
use BabDev\WebSocket\Server\RequestAwareServerComponentInterface;
use Psr\Http\Message\RequestInterface;

/**
 * The parse HTTP request server component transforms the incoming HTTP request into a {@see RequestInterface} object.
 */
final class ParseHttpRequest implements RawDataServerComponentInterface
{
    use ClosesConnectionWithResponse;

    public function __construct(
        private readonly RequestAwareServerComponentInterface $component,
        private readonly RequestParserInterface $requestParser = new RequestParser(),
    ) {
    }

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(ConnectionInterface $connection): void
    {
        $connection->getAttributeStore()->set('http.headers_received', false);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(ConnectionInterface $connection, string $data): void
    {
        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            $this->component->onMessage($connection, $data);

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

        $this->component->onOpen($connection, $request);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(ConnectionInterface $connection): void
    {
        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            $this->component->onClose($connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(ConnectionInterface $connection, \Throwable $throwable): void
    {
        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            $this->component->onError($connection, $throwable);
        } else {
            $this->close($connection, 500);
        }
    }
}
