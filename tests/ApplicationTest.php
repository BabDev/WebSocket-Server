<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests;

use BabDev\WebSocket\Server\Application;
use BabDev\WebSocket\Server\Http\Exception\InvalidRequestTimeout;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\EventLoop\TimerInterface;

final class ApplicationTest extends TestCase
{
    private string $socketPath;

    protected function setUp(): void
    {
        $this->socketPath = sys_get_temp_dir().'/babdev-websocket-'.bin2hex(random_bytes(4)).'.sock';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }
    }

    #[TestDox('Closes connections which do not send a request before the request timeout')]
    public function testRequestTimeoutClosesStalledConnection(): void
    {
        $loop = new StreamSelectLoop();

        $client = $this->runWithStalledClient(new Application("unix://{$this->socketPath}", [], $loop)->withRequestTimeout(0.05), $loop);

        $response = (string) stream_get_contents($client);

        $this->assertStringStartsWith('HTTP/1.1 408 ', $response);
        $this->assertTrue(feof($client), 'The connection should be closed by the server.');
    }

    #[TestDox('Keeps stalled connections open when the request timeout is disabled')]
    public function testRequestTimeoutCanBeDisabled(): void
    {
        $loop = new StreamSelectLoop();

        $client = $this->runWithStalledClient(new Application("unix://{$this->socketPath}", [], $loop)->withRequestTimeout(null), $loop);

        $this->assertSame('', stream_get_contents($client));
        $this->assertFalse(feof($client), 'The connection should remain open.');
    }

    #[TestDox('Rejects a request timeout which is not a positive number')]
    public function testRequestTimeoutMustBePositive(): void
    {
        $this->expectException(InvalidRequestTimeout::class);

        new Application("unix://{$this->socketPath}", [], $this->createStub(LoopInterface::class))->withRequestTimeout(0.0);
    }

    #[TestDox('Enables the keepalive when configured')]
    public function testKeepAliveIsEnabled(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with(15, $this->isCallable())
            ->willReturn($this->createStub(TimerInterface::class));

        $loop->expects($this->once())
            ->method('run');

        new Application("unix://{$this->socketPath}", [], $loop)->withKeepAlive(15)->run();
    }

    #[TestDox('Does not enable the keepalive by default')]
    public function testKeepAliveIsDisabledByDefault(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())
            ->method('addPeriodicTimer');

        $loop->expects($this->once())
            ->method('run');

        new Application("unix://{$this->socketPath}", [], $loop)->run();
    }

    #[TestDox('Registers handlers for the SIGTERM and SIGINT signals')]
    public function testRegistersShutdownSignals(): void
    {
        if (!\defined('SIGTERM') || !\defined('SIGINT')) {
            self::markTestSkipped('The "pcntl" extension is required to test signal handling.');
        }

        $handlers = [];

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->exactly(2))
            ->method('addSignal')
            ->willReturnCallback(static function (int $signal, callable $listener) use (&$handlers): void {
                $handlers[$signal] = $listener;
            });

        $loop->expects($this->exactly(2))
            ->method('removeSignal')
            ->with($this->logicalOr(\SIGTERM, \SIGINT), $this->isCallable());

        // With no open connections, the server stops the loop immediately when shut down
        $loop->expects($this->once())
            ->method('stop');

        new Application("unix://{$this->socketPath}", [], $loop)->run();

        $this->assertSame([\SIGTERM, \SIGINT], array_keys($handlers));

        $handlers[\SIGTERM]();
    }

    #[TestDox('Does not register signal handlers when the shutdown timeout is disabled')]
    public function testDoesNotRegisterShutdownSignalsWhenDisabled(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())
            ->method('addSignal');

        $loop->expects($this->once())
            ->method('run');

        new Application("unix://{$this->socketPath}", [], $loop)->withShutdownTimeout(null)->run();
    }

    #[TestDox('Rejects a blocked client address forwarded by a trusted proxy')]
    public function testBlocksForwardedClientAddressFromTrustedProxy(): void
    {
        $response = $this->sendRequestWithForwardedFor(
            new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
                ->withTrustedProxies(['127.0.0.1'])
                ->blockAddress('203.0.113.5'),
            $loop,
            $port,
            '203.0.113.5',
        );

        $this->assertStringStartsWith('HTTP/1.1 403 ', $response);
    }

    #[TestDox('Allows a client address forwarded by a trusted proxy which is not blocked')]
    public function testAllowsForwardedClientAddressFromTrustedProxy(): void
    {
        $response = $this->sendRequestWithForwardedFor(
            new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
                ->withTrustedProxies(['127.0.0.1'])
                ->blockAddress('203.0.113.5'),
            $loop,
            $port,
            '198.51.100.1',
        );

        $this->assertStringStartsWith('HTTP/1.1 405 ', $response);
    }

    #[TestDox('Rejects a blocked client connecting directly to the server')]
    public function testBlocksDirectClientAddress(): void
    {
        $response = $this->sendRequestWithForwardedFor(
            new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
                ->blockAddress('127.0.0.1'),
            $loop,
            $port,
            '198.51.100.1',
        );

        $this->assertStringStartsWith('HTTP/1.1 403 ', $response);
    }

    #[TestDox('Ignores the forwarding header when no proxies are trusted')]
    public function testIgnoresForwardedClientAddressWithoutTrustedProxies(): void
    {
        $response = $this->sendRequestWithForwardedFor(
            new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
                ->blockAddress('203.0.113.5'),
            $loop,
            $port,
            '203.0.113.5',
        );

        $this->assertStringStartsWith('HTTP/1.1 405 ', $response);
    }

    /**
     * Runs the application and sends a request with an "X-Forwarded-For" header, returning the response.
     *
     * The request uses the POST method so a client which passes the blocked address check receives a predictable
     * "405 Method Not Allowed" response from the WebSocket handshake, while a blocked client receives a "403 Forbidden" response.
     */
    private function sendRequestWithForwardedFor(Application $application, LoopInterface $loop, int $port, string $forwardedFor): string
    {
        $client = null;

        $loop->addTimer(0.01, static function () use (&$client, $port, $forwardedFor): void {
            $client = stream_socket_client("tcp://127.0.0.1:{$port}");

            if (\is_resource($client)) {
                fwrite($client, "POST / HTTP/1.1\r\nHost: localhost\r\nX-Forwarded-For: {$forwardedFor}\r\n\r\n");
            }
        });

        $loop->addTimer(0.25, static function () use ($loop): void {
            $loop->stop();
        });

        // Signal handlers are process-wide and would outlive this test's event loop
        $application->withShutdownTimeout(null)->run();

        if (!\is_resource($client)) {
            self::fail('The client could not connect to the server.');
        }

        stream_set_blocking($client, false);

        return (string) stream_get_contents($client);
    }

    private function findAvailablePort(): int
    {
        $socket = stream_socket_server('tcp://127.0.0.1:0');

        if (false === $socket) {
            self::fail('Could not find an available port.');
        }

        $port = parse_url('tcp://'.stream_socket_get_name($socket, false), \PHP_URL_PORT);

        fclose($socket);

        if (!\is_int($port)) {
            self::fail('Could not find an available port.');
        }

        return $port;
    }

    /**
     * Runs the application long enough for a client which never sends a request to connect and be timed out.
     *
     * @return resource
     */
    private function runWithStalledClient(Application $application, LoopInterface $loop)
    {
        $client = null;

        $loop->addTimer(0.01, function () use (&$client): void {
            $client = stream_socket_client("unix://{$this->socketPath}");
        });

        $loop->addTimer(0.25, static function () use ($loop): void {
            $loop->stop();
        });

        // Signal handlers are process-wide and would outlive this test's event loop
        $application->withShutdownTimeout(null)->run();

        if (!\is_resource($client)) {
            self::fail('The client could not connect to the server.');
        }

        stream_set_blocking($client, false);

        return $client;
    }
}
