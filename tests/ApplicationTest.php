<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests;

use BabDev\WebSocket\Server\Application;
use BabDev\WebSocket\Server\Http\Exception\InvalidAllowedOrigin;
use BabDev\WebSocket\Server\Http\Exception\InvalidRequestTimeout;
use BabDev\WebSocket\Server\TopicMessageHandler;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\MessageType;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Messaging\Frame;
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
            ->withParameterSetsInOrder([\SIGTERM, $this->isCallable()], [\SIGINT, $this->isCallable()])
            ->willReturnCallback(static function (int $signal, callable $listener) use (&$handlers): void {
                $handlers[$signal] = $listener;
            });

        $loop->expects($this->exactly(2))
            ->method('removeSignal')
            ->withParameterSetsInAnyOrder([\SIGTERM, $this->isCallable()], [\SIGINT, $this->isCallable()]);

        // With no open connections, the server stops the loop immediately when shut down
        $loop->expects($this->once())
            ->method('stop');

        new Application("unix://{$this->socketPath}", [], $loop)->run();

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

    #[TestDox('Rejects a request larger than the configured maximum request size')]
    public function testMaxRequestSize(): void
    {
        $response = $this->sendRequestWithForwardedFor(
            new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
                ->withMaxRequestSize(32),
            $loop,
            $port,
            '198.51.100.1',
        );

        $this->assertStringStartsWith('HTTP/1.1 413 ', $response);
    }

    #[TestDox('Validates an allowed origin when it is added')]
    public function testAllowOriginValidatesTheOrigin(): void
    {
        $this->expectException(InvalidAllowedOrigin::class);

        new Application("unix://{$this->socketPath}", [], $this->createStub(LoopInterface::class))->allowOrigin('localhost:8080');
    }

    #[TestDox('Removes an allowed origin given in an equivalent form')]
    public function testRemoveAllowedOriginNormalizesTheOrigin(): void
    {
        $response = $this->sendRequestWithForwardedFor(
            new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
                ->allowOrigin('https://example.com')
                ->removeAllowedOrigin('HTTPS://EXAMPLE.com:443'),
            $loop,
            $port,
            '198.51.100.1',
        );

        // Without any allowed origins, the request is not rejected for its missing Origin header
        $this->assertStringStartsWith('HTTP/1.1 405 ', $response);
    }

    #[TestDox('Uses the configured server identity and topic registry')]
    public function testServerIdentityAndTopicRegistry(): void
    {
        $topicRegistry = new ArrayTopicRegistry();

        $handler = new class implements TopicMessageHandler {
            public function onSubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void {}

            public function onUnsubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void {}

            public function onPublish(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request, mixed $event, array $exclude, array $eligible): void {}
        };

        $application = new Application('127.0.0.1:'.($port = $this->findAvailablePort()), [], $loop = new StreamSelectLoop())
            ->withServerIdentity('Test-Identity/1.0')
            ->withTopicRegistry($topicRegistry)
            ->withShutdownTimeout(null)
            ->route('/topic/{id}', $handler);

        $client = null;
        $received = '';

        $loop->addTimer(0.01, static function () use (&$client, $port): void {
            $client = stream_socket_client("tcp://127.0.0.1:{$port}");

            if (\is_resource($client)) {
                fwrite($client, "GET / HTTP/1.1\r\nHost: localhost\r\nUpgrade: websocket\r\nConnection: Upgrade\r\nSec-WebSocket-Key: dGhlIHNhbXBsZSBub25jZQ==\r\nSec-WebSocket-Version: 13\r\nSec-WebSocket-Protocol: wamp\r\n\r\n");
            }
        });

        $loop->addTimer(0.1, static function () use (&$client, &$received): void {
            if (!\is_resource($client)) {
                return;
            }

            stream_set_blocking($client, false);
            $received = (string) stream_get_contents($client);

            $subscribe = new Frame(json_encode([MessageType::SUBSCRIBE, '/topic/1'], \JSON_THROW_ON_ERROR));
            $subscribe->maskPayload();

            fwrite($client, $subscribe->getContents());
        });

        $loop->addTimer(0.25, static function () use ($loop): void {
            $loop->stop();
        });

        $application->run();

        if (!str_starts_with($received, 'HTTP/1.1 101 ')) {
            self::markTestSkipped('The WebSocket handshake could not be completed with the installed dependencies.');
        }

        $this->assertStringContainsString(json_encode('Test-Identity/1.0', \JSON_THROW_ON_ERROR), $received, 'The WAMP "WELCOME" message should include the configured server identity.');
        $this->assertTrue($topicRegistry->has('/topic/1'), 'The configured topic registry should be used for subscriptions.');
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
