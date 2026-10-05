<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\ReactSocketConnection;
use BabDev\WebSocket\Server\Connection\RemoteAddress;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

/**
 * The {@see ReactPhpServer} is an implementation of the server interface which runs a WebSocket server stack using
 * the ReactPHP library.
 */
final readonly class ReactPhpServer implements Server
{
    /**
     * The default number of bytes which can be written to a connection after its write buffer is full before the
     * connection is closed.
     */
    public const int DEFAULT_WRITE_BUFFER_LIMIT = 1_048_576;

    private LoopInterface $loop;

    /**
     * @param int<1, max>|null $writeBufferLimit
     */
    public function __construct(
        private ServerMiddleware $middleware,
        private ServerInterface $socket,
        ?LoopInterface $loop = null,
        private ?LoggerInterface $logger = null,
        private ?int $writeBufferLimit = self::DEFAULT_WRITE_BUFFER_LIMIT,
    ) {
        gc_enable();
        set_time_limit(0);
        ob_implicit_flush();

        $this->loop = $loop ?? Loop::get();

        $socket->on('connection', $this->onConnection(...));
    }

    public function run(): void
    {
        $this->loop->run();
    }

    /**
     * Handles a new connection on the provided {@see ServerInterface} instance.
     *
     * @internal
     */
    public function onConnection(ConnectionInterface $connection): void
    {
        $uri = $connection->getRemoteAddress();

        $decoratedConnection = new ReactSocketConnection($connection, new ArrayAttributeStore(), $this->writeBufferLimit);
        $decoratedConnection->getAttributeStore()->set('resource_id', (int) $connection->stream);

        if (null !== $uri && null !== $remoteAddress = RemoteAddress::fromUri($uri)) {
            $decoratedConnection->getAttributeStore()->set('remote_address', $remoteAddress);
        }

        $connection->on(
            'data',
            function (string $data) use ($decoratedConnection): void {
                $this->onData($decoratedConnection, $data);
            },
        );

        $connection->on(
            'close',
            function () use ($decoratedConnection): void {
                $this->onEnd($decoratedConnection);
            },
        );

        $connection->on(
            'error',
            function (\Throwable $throwable) use ($decoratedConnection): void {
                $this->onError($decoratedConnection, $throwable);
            },
        );

        try {
            $this->middleware->onOpen($decoratedConnection);
        } catch (\Throwable $throwable) {
            $this->onError($decoratedConnection, $throwable);
        }
    }

    /**
     * Handles incoming data on the provided {@see ServerInterface} instance.
     *
     * @internal
     */
    public function onData(Connection $connection, string $data): void
    {
        try {
            $this->middleware->onMessage($connection, $data);
        } catch (\Throwable $throwable) {
            $this->onError($connection, $throwable);
        }
    }

    /**
     * Handles the {@see ServerInterface} instance being closed.
     *
     * @internal
     */
    public function onEnd(Connection $connection): void
    {
        try {
            $this->middleware->onClose($connection);
        } catch (\Throwable $throwable) {
            $this->onError($connection, $throwable);
        }
    }

    /**
     * Handles an uncaught Throwable.
     *
     * @internal
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        try {
            $this->middleware->onError($connection, $throwable);
        } catch (\Throwable $handlerThrowable) {
            $this->logger?->error(
                'An uncaught Throwable was raised while handling an error for a connection, the connection will be closed.',
                [
                    'exception' => $handlerThrowable,
                    'original_exception' => $throwable,
                    'resource_id' => $connection->getAttributeStore()->get('resource_id'),
                ],
            );

            $this->closeAfterFailure($connection);
        }
    }

    /**
     * Closes a connection after the middleware stack failed to handle an error for it.
     *
     * At this point the state of the connection is unknown, so any failure closing it is reported and otherwise
     * ignored to protect the event loop.
     */
    private function closeAfterFailure(Connection $connection): void
    {
        try {
            $connection->close();
        } catch (\Throwable $throwable) {
            $this->logger?->error(
                'An uncaught Throwable was raised while closing a connection after an error.',
                [
                    'exception' => $throwable,
                    'resource_id' => $connection->getAttributeStore()->get('resource_id'),
                ],
            );
        }
    }
}
