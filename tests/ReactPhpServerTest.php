<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\ServerMiddleware;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\SocketServer;

final class ReactPhpServerTest extends TestCase
{
    private MockObject&ServerMiddleware $middleware;

    private SocketServer $socket;

    private ReactPhpServer $server;

    private int $port;

    public static function setUpBeforeClass(): void
    {
        Loop::set(new StreamSelectLoop());
    }

    public static function tearDownAfterClass(): void
    {
        (new \ReflectionClass(Loop::class))->getProperty('instance')->setValue(null);
    }

    protected function setUp(): void
    {
        $this->middleware = $this->createMock(ServerMiddleware::class);

        $this->socket = new SocketServer('127.0.0.1:0', [], Loop::get());

        $uri = $this->socket->getAddress();

        if (!\is_string($uri)) {
            self::fail('Could not get socket server address');
        }

        $port = parse_url((str_contains($uri, '://') ? '' : 'tcp://').$uri, \PHP_URL_PORT);

        if (!\is_int($port)) {
            self::fail('Could not extract port from socket server address');
        }

        $this->port = $port;

        $this->server = new ReactPhpServer($this->middleware, $this->socket, Loop::get());
    }

    protected function tickLoop(LoopInterface $loop): void
    {
        $loop->futureTick(function () use ($loop): void {
            $loop->stop();
        });

        $loop->run();
    }

    #[TestDox('Handles a new connection being opened')]
    public function testOnOpen(): void
    {
        $this->middleware->expects($this->once())
            ->method('onOpen')
            ->with($this->isInstanceOf(Connection::class));

        stream_socket_client("tcp://localhost:{$this->port}");

        $this->tickLoop(Loop::get());
    }

    #[TestDox('Handles incoming data on the connection')]
    public function testOnData(): void
    {
        $message = 'Hello World!';

        $this->middleware->expects($this->once())
            ->method('onMessage')
            ->with($this->isInstanceOf(Connection::class), $message);

        $client = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);

        if (false === $client) {
            self::fail(sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
        }

        socket_set_option($client, \SOL_SOCKET, \SO_REUSEADDR, 1);
        socket_set_option($client, \SOL_SOCKET, \SO_SNDBUF, 4096);
        socket_set_block($client);
        socket_connect($client, 'localhost', $this->port);

        $this->tickLoop(Loop::get());

        socket_write($client, $message);

        $this->tickLoop(Loop::get());

        socket_shutdown($client, 1);
        socket_shutdown($client, 0);
        socket_close($client);

        $this->tickLoop(Loop::get());
    }

    #[TestDox('Handles a connection being closed')]
    public function testOnEnd(): void
    {
        $this->middleware->expects($this->once())
            ->method('onClose')
            ->with($this->isInstanceOf(Connection::class));

        $client = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);

        if (false === $client) {
            self::fail(sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
        }

        socket_set_option($client, \SOL_SOCKET, \SO_REUSEADDR, 1);
        socket_set_option($client, \SOL_SOCKET, \SO_SNDBUF, 4096);
        socket_set_block($client);
        socket_connect($client, 'localhost', $this->port);

        $this->tickLoop(Loop::get());

        socket_shutdown($client, 1);
        socket_shutdown($client, 0);
        socket_close($client);

        $this->tickLoop(Loop::get());
    }

    #[TestDox('Handles an uncaught Throwable while processing incoming data on the connection')]
    public function testOnError(): void
    {
        $exception = new \RuntimeException('Testing');

        $message = 'Hello World!';

        $this->middleware->expects($this->once())
            ->method('onMessage')
            ->with($this->isInstanceOf(Connection::class), $message)
            ->willThrowException($exception);

        $this->middleware->expects($this->once())
            ->method('onError')
            ->with($this->isInstanceOf(Connection::class), $exception);

        $client = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);

        if (false === $client) {
            self::fail(sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
        }

        socket_set_option($client, \SOL_SOCKET, \SO_REUSEADDR, 1);
        socket_set_option($client, \SOL_SOCKET, \SO_SNDBUF, 4096);
        socket_set_block($client);
        socket_connect($client, 'localhost', $this->port);

        $this->tickLoop(Loop::get());

        socket_write($client, $message);

        $this->tickLoop(Loop::get());

        socket_shutdown($client, 1);
        socket_shutdown($client, 0);
        socket_close($client);

        $this->tickLoop(Loop::get());
    }
}
