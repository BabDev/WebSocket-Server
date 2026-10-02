<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Http\Exception\InvalidRequestTimeout;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MessageTooLarge;
use BabDev\WebSocket\Server\Http\GuzzleRequestParser;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\ServerMiddleware;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * The parse HTTP request server middleware transforms the incoming HTTP request into a {@see RequestInterface} object.
 */
final class ParseHttpRequest implements ServerMiddleware
{
    use ClosesConnectionWithResponse;

    /**
     * @var \SplObjectStorage<Connection, TimerInterface>
     */
    private readonly \SplObjectStorage $requestTimers;

    private ?LoopInterface $loop = null;

    private float $requestTimeout = 0.0;

    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly RequestParser $requestParser = new GuzzleRequestParser(),
    ) {
        $this->requestTimers = new \SplObjectStorage();
    }

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void
    {
        $connection->getAttributeStore()->set('http.headers_received', false);

        if ($this->loop instanceof LoopInterface) {
            $this->requestTimers->offsetSet(
                $connection,
                $this->loop->addTimer($this->requestTimeout, function () use ($connection): void {
                    $this->onRequestTimeout($connection);
                }),
            );
        }
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
            if (!($request = $this->requestParser->parse($connection, $data)) instanceof RequestInterface) {
                return;
            }
        } catch (MalformedRequest $exception) {
            $this->close($connection, 400);

            throw $exception;
        } catch (MessageTooLarge $exception) {
            $this->close($connection, 413);

            throw $exception;
        }

        $this->cancelRequestTimer($connection);

        $connection->getAttributeStore()->set('http.headers_received', true);
        $connection->getAttributeStore()->set('http.request', $request);

        $this->middleware->onOpen($connection);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        $this->cancelRequestTimer($connection);

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

    /**
     * Enables the request timeout, closing connections which do not send a complete HTTP request within the given time.
     *
     * The timeout applies to connections opened after it is enabled. It measures the total time to receive the request,
     * so a client sending the request slowly is closed even if it sends data before the timeout expires.
     *
     * @throws InvalidRequestTimeout if the timeout is not a positive number
     */
    public function enableRequestTimeout(LoopInterface $loop, float $timeout = 10.0): void
    {
        if ($timeout <= 0) {
            throw new InvalidRequestTimeout($timeout, \sprintf('The request timeout must be a positive number, %s given.', $timeout));
        }

        $this->loop = $loop;
        $this->requestTimeout = $timeout;
    }

    private function onRequestTimeout(Connection $connection): void
    {
        $this->requestTimers->offsetUnset($connection);

        if (true === $connection->getAttributeStore()->get('http.headers_received')) {
            return;
        }

        $connection->getAttributeStore()->remove('http.buffer');

        try {
            $this->close($connection, 408);
        } catch (\Throwable) {
            // This runs from an event loop timer, so a failure to respond to a client being dropped must not reach the loop
        }
    }

    private function cancelRequestTimer(Connection $connection): void
    {
        if (!$this->requestTimers->offsetExists($connection)) {
            return;
        }

        $this->loop?->cancelTimer($this->requestTimers[$connection]);
        $this->requestTimers->offsetUnset($connection);
    }
}
