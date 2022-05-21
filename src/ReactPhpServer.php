<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Connection\ReactSocketConnection;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\ConnectionInterface as ReactSocketConnectionInterface;
use React\Socket\ServerInterface as ReactSocketServerInterface;

/**
 * The {@see ReactPhpServer} is an implementation of the server interface which runs a WebSocket server stack using
 * the ReactPHP library.
 */
final class ReactPhpServer implements ServerInterface
{
    private readonly LoopInterface $loop;

    public function __construct(
        private readonly RawDataServerComponentInterface $component,
        private readonly ReactSocketServerInterface $socket,
        ?LoopInterface $loop = null
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
     * Handles a new connection on the provided {@see ReactSocketServerInterface} instance.
     *
     * @internal
     */
    public function onConnection(ReactSocketConnectionInterface $connection): void
    {
        $uri = $connection->getRemoteAddress();

        $decoratedConnection = new ReactSocketConnection($connection, new AttributeStore());
        $decoratedConnection->getAttributeStore()->set('resource_id', (int) $connection->stream);
        $decoratedConnection->getAttributeStore()->set(
            'remote_address',
            trim(
                parse_url((!str_contains($uri, '://') ? 'tcp://' : '').$uri, \PHP_URL_HOST),
                '[]'
            )
        );

        $this->component->onOpen($decoratedConnection);

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
    }

    /**
     * Handles incoming data on the provided {@see ReactSocketServerInterface} instance.
     *
     * @internal
     */
    public function onData(ConnectionInterface $connection, string $data): void
    {
        try {
            $this->component->onMessage($connection, $data);
        } catch (\Throwable $throwable) {
            $this->onError($connection, $throwable);
        }
    }

    /**
     * Handles the {@see ReactSocketServerInterface} instance being closed.
     *
     * @internal
     */
    public function onEnd(ConnectionInterface $connection): void
    {
        try {
            $this->component->onClose($connection);
        } catch (\Throwable $throwable) {
            $this->onError($connection, $throwable);
        }
    }

    /**
     * Handles an uncaught Throwable.
     *
     * @internal
     */
    public function onError(ConnectionInterface $connection, \Throwable $throwable): void
    {
        $this->component->onError($connection, $throwable);
    }
}
