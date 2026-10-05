<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Connection\ReactSocketConnection;
use BabDev\WebSocket\Server\Connection\RemoteAddress;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;
use React\Socket\Connection as ReactConnection;
use React\Socket\ConnectionInterface;
use React\Socket\ServerInterface;

/**
 * The {@see ReactPhpServer} is an implementation of the server interface which runs a WebSocket server stack using
 * the ReactPHP library.
 */
final class ReactPhpServer implements Server
{
    /**
     * The default number of bytes which can be written to a connection after its write buffer is full before the
     * connection is closed.
     */
    public const int DEFAULT_WRITE_BUFFER_LIMIT = 1_048_576;

    private readonly LoopInterface $loop;

    /**
     * @var \SplObjectStorage<ConnectionInterface, null>
     */
    private readonly \SplObjectStorage $connections;

    private ?TimerInterface $shutdownTimer = null;

    private bool $shuttingDown = false;

    /**
     * @param int<1, max>|null $writeBufferLimit
     */
    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly ServerInterface $socket,
        ?LoopInterface $loop = null,
        private readonly ?LoggerInterface $logger = null,
        private readonly ?int $writeBufferLimit = self::DEFAULT_WRITE_BUFFER_LIMIT,
    ) {
        $this->loop = $loop ?? Loop::get();
        $this->connections = new \SplObjectStorage();

        $socket->on('connection', $this->onConnection(...));
    }

    public function run(): void
    {
        gc_enable();
        set_time_limit(0);
        ob_implicit_flush();

        $this->loop->run();
    }

    public function shutdown(float $timeout = 5.0): void
    {
        if ($this->shuttingDown) {
            return;
        }

        $this->shuttingDown = true;

        $this->socket->close();

        // Closing a connection removes it from the storage, so the connections are copied before iterating
        foreach (iterator_to_array($this->connections, false) as $connection) {
            $connection->end();
        }

        if (0 === $this->connections->count()) {
            $this->loop->stop();

            return;
        }

        $this->shutdownTimer = $this->loop->addTimer($timeout, function (): void {
            $this->shutdownTimer = null;

            foreach (iterator_to_array($this->connections, false) as $connection) {
                $connection->close();
            }

            $this->loop->stop();
        });
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
        $decoratedConnection->getAttributeStore()->set(AttributeKey::RESOURCE_ID, $this->getResourceId($connection));

        if (null !== $uri && null !== $remoteAddress = RemoteAddress::fromUri($uri)) {
            $decoratedConnection->getAttributeStore()->set(AttributeKey::REMOTE_ADDRESS, $remoteAddress);
        }

        $connection->on(
            'data',
            function (string $data) use ($decoratedConnection): void {
                $this->onData($decoratedConnection, $data);
            },
        );

        $this->connections->offsetSet($connection);

        $connection->on(
            'close',
            function () use ($connection, $decoratedConnection): void {
                $this->connections->offsetUnset($connection);

                $this->onEnd($decoratedConnection);

                if ($this->shuttingDown && 0 === $this->connections->count()) {
                    $this->stopAfterShutdown();
                }
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
                    'resource_id' => $connection->getAttributeStore()->get(AttributeKey::RESOURCE_ID),
                ],
            );

            $this->closeAfterFailure($connection);
        }
    }

    /**
     * The `stream` property is only available on the {@see ReactConnection} class, other implementations use their object ID.
     */
    private function getResourceId(ConnectionInterface $connection): int
    {
        if ($connection instanceof ReactConnection && \is_resource($connection->stream)) {
            return (int) $connection->stream;
        }

        return spl_object_id($connection);
    }

    private function stopAfterShutdown(): void
    {
        if ($this->shutdownTimer instanceof TimerInterface) {
            $this->loop->cancelTimer($this->shutdownTimer);
            $this->shutdownTimer = null;
        }

        $this->loop->stop();
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
                    'resource_id' => $connection->getAttributeStore()->get(AttributeKey::RESOURCE_ID),
                ],
            );
        }
    }
}
