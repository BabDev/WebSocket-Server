<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\ReactPhpServer;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Tests\Fixtures\RecordingLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\Socket\SocketServer;

final class ReactPhpServerTest extends TestCase
{
    private readonly MockObject&ServerMiddleware $middleware;

    private readonly RecordingLogger $logger;

    private readonly int $port;

    private readonly SocketServer $socket;

    public static function setUpBeforeClass(): void
    {
        Loop::set(new StreamSelectLoop());
    }

    public static function tearDownAfterClass(): void
    {
        new \ReflectionClass(Loop::class)->setStaticPropertyValue('instance', null);
    }

    protected function setUp(): void
    {
        $this->middleware = $this->createMock(ServerMiddleware::class);
        $this->logger = new RecordingLogger();

        $this->socket = $socket = new SocketServer('127.0.0.1:0', [], Loop::get());

        $uri = $socket->getAddress();

        if (!\is_string($uri)) {
            self::fail('Could not get socket server address');
        }

        $port = parse_url((str_contains($uri, '://') ? '' : 'tcp://').$uri, \PHP_URL_PORT);

        if (!\is_int($port)) {
            self::fail('Could not extract port from socket server address');
        }

        $this->port = $port;

        new ReactPhpServer($this->middleware, $socket, Loop::get(), $this->logger);
    }

    protected function tearDown(): void
    {
        // Stop accepting connections so a later test's loop iterations cannot reach this test's middleware
        $this->socket->close();

        $this->tickLoop(Loop::get());
    }

    /**
     * Runs the loop long enough for pending socket I/O to be processed.
     *
     * A single tick is not enough, as the loop polls the streams without waiting and data written by the client
     * may not be readable yet.
     */
    protected function tickLoop(LoopInterface $loop): void
    {
        $loop->addTimer(0.05, static function () use ($loop): void {
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
            self::fail(\sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
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
            self::fail(\sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
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
            self::fail(\sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
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

    #[TestDox('Handles an uncaught Throwable while opening the connection')]
    public function testOnOpenError(): void
    {
        $exception = new \RuntimeException('Testing');

        $this->middleware->expects($this->once())
            ->method('onOpen')
            ->with($this->isInstanceOf(Connection::class))
            ->willThrowException($exception);

        $this->middleware->expects($this->once())
            ->method('onError')
            ->with($this->isInstanceOf(Connection::class), $exception);

        stream_socket_client("tcp://localhost:{$this->port}");

        $this->tickLoop(Loop::get());

        $this->assertSame([], $this->logger->records);
    }

    #[TestDox('Logs and closes the connection when the error handler throws while processing incoming data')]
    public function testOnErrorFailureWhileProcessingData(): void
    {
        $exception = new \RuntimeException('Testing');
        $handlerException = new \LogicException('Error handler failure');

        $message = 'Hello World!';

        $this->middleware->expects($this->once())
            ->method('onMessage')
            ->with($this->isInstanceOf(Connection::class), $message)
            ->willThrowException($exception);

        $this->middleware->expects($this->once())
            ->method('onError')
            ->with($this->isInstanceOf(Connection::class), $exception)
            ->willThrowException($handlerException);

        // The server closes the connection itself, the client never disconnects before the assertions
        $this->middleware->expects($this->once())
            ->method('onClose')
            ->with($this->isInstanceOf(Connection::class));

        $client = $this->connectClient();

        $this->tickLoop(Loop::get());

        socket_write($client, $message);

        $this->tickLoop(Loop::get());

        $this->assertCount(1, $this->logger->records);
        $this->assertSame('error', $this->logger->records[0]['level']);
        $this->assertSame($handlerException, $this->logger->records[0]['context']['exception']);
        $this->assertSame($exception, $this->logger->records[0]['context']['original_exception']);

        socket_close($client);

        $this->tickLoop(Loop::get());
    }

    #[TestDox('Logs and closes the connection when the error handler throws while opening the connection')]
    public function testOnErrorFailureWhileOpeningConnection(): void
    {
        $exception = new \RuntimeException('Testing');
        $handlerException = new \LogicException('Error handler failure');

        $this->middleware->expects($this->once())
            ->method('onOpen')
            ->with($this->isInstanceOf(Connection::class))
            ->willThrowException($exception);

        $this->middleware->expects($this->once())
            ->method('onError')
            ->with($this->isInstanceOf(Connection::class), $exception)
            ->willThrowException($handlerException);

        $this->middleware->expects($this->once())
            ->method('onClose')
            ->with($this->isInstanceOf(Connection::class));

        $client = $this->connectClient();

        $this->tickLoop(Loop::get());

        $this->assertCount(1, $this->logger->records);
        $this->assertSame($handlerException, $this->logger->records[0]['context']['exception']);
        $this->assertSame($exception, $this->logger->records[0]['context']['original_exception']);

        socket_close($client);

        $this->tickLoop(Loop::get());
    }

    /**
     * @return \Generator<string, array{non-empty-string, array<string, mixed>, non-empty-string, non-empty-string}>
     */
    public static function dataRemoteAddress(): \Generator
    {
        yield 'IPv4 client' => ['127.0.0.1:0', [], '127.0.0.1', '127.0.0.1'];

        yield 'IPv6 client' => ['[::1]:0', ['tcp' => ['ipv6_v6only' => true]], '[::1]', '::1'];

        // A dual-stack server reports IPv4 clients using an IPv4-mapped IPv6 address
        yield 'IPv4 client on a dual-stack server' => ['[::]:0', ['tcp' => ['ipv6_v6only' => false]], '127.0.0.1', '127.0.0.1'];
    }

    /**
     * @param non-empty-string     $listenUri
     * @param array<string, mixed> $context
     * @param non-empty-string     $clientHost
     * @param non-empty-string     $expectedAddress
     */
    #[TestDox('Stores the normalized remote address for the connection')]
    #[DataProvider('dataRemoteAddress')]
    public function testStoresRemoteAddress(string $listenUri, array $context, string $clientHost, string $expectedAddress): void
    {
        try {
            $socket = new SocketServer($listenUri, $context, Loop::get());
        } catch (\RuntimeException $exception) {
            self::markTestSkipped(\sprintf('Cannot listen on "%s": %s', $listenUri, $exception->getMessage()));
        }

        $port = parse_url((string) $socket->getAddress(), \PHP_URL_PORT);

        if (!\is_int($port)) {
            $socket->close();

            self::fail('Could not extract port from socket server address');
        }

        // The connection is made to this test's server, not the one created in setUp()
        $this->middleware->expects($this->never())
            ->method('onOpen');

        $remoteAddress = null;

        $middleware = $this->createMock(ServerMiddleware::class);
        $middleware->expects($this->once())
            ->method('onOpen')
            ->willReturnCallback(static function (Connection $connection) use (&$remoteAddress): void {
                $remoteAddress = $connection->getAttributeStore()->get('remote_address');
            });

        new ReactPhpServer($middleware, $socket, Loop::get(), $this->logger);

        $client = stream_socket_client("tcp://{$clientHost}:{$port}");

        $this->tickLoop(Loop::get());

        $socket->close();

        if (\is_resource($client)) {
            fclose($client);
        }

        $this->tickLoop(Loop::get());

        $this->assertSame($expectedAddress, $remoteAddress);
    }

    private function connectClient(): \Socket
    {
        $client = socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);

        if (false === $client) {
            self::fail(\sprintf('Could not create the socket for testing: %s', socket_strerror(socket_last_error())));
        }

        socket_set_option($client, \SOL_SOCKET, \SO_REUSEADDR, 1);
        socket_set_option($client, \SOL_SOCKET, \SO_SNDBUF, 4096);
        socket_set_block($client);
        socket_connect($client, 'localhost', $this->port);

        return $client;
    }
}
