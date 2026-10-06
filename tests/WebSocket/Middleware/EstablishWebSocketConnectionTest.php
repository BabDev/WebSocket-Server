<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WebSocket\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Tests\Fixtures\RecordingConnection;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Ratchet\RFC6455\Messaging\Frame;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

#[AllowMockObjectsWithoutExpectations]
final class EstablishWebSocketConnectionTest extends TestCase
{
    #[TestDox('Handles activity during the lifecycle of a connection')]
    public function testConnectionLifecycle(): void
    {
        /** @var Stub&RequestInterface $request */
        $request = $this->createStub(RequestInterface::class);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.request', $request);

        /** @var MockObject&StreamInterface $stream */
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn('');

        /** @var MockObject&ResponseInterface $response */
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('withoutHeader')
            ->with('X-Powered-By')
            ->willReturnSelf();

        $response->expects($this->once())
            ->method('getProtocolVersion')
            ->willReturn('1.1');

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(101);

        $response->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn('Switching Protocols');

        $response->expects($this->once())
            ->method('getHeaders')
            ->willReturn([]);

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        /** @var MockObject&NegotiatorInterface $negotiator */
        $negotiator = $this->createMock(NegotiatorInterface::class);
        $negotiator->expects($this->once())
            ->method('handshake')
            ->with($request)
            ->willReturn($response);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($this->isInstanceOf(WebSocketConnection::class));

        $middleware = new EstablishWebSocketConnection($decoratedMiddleware, $negotiator);
        $middleware->onOpen($connection);
        $middleware->onMessage($connection, 'Incoming');

        $exception = new \RuntimeException('Testing');

        $decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($this->isInstanceOf(WebSocketConnection::class), $exception);

        $middleware->onError($connection, $exception);

        $decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($this->isInstanceOf(WebSocketConnection::class));

        $middleware->onClose($connection);
    }

    #[TestDox('Handles a new connection being opened when required middleware have not run before this middleware')]
    public function testOnOpenWithoutRequest(): void
    {
        /** @var MockObject&NegotiatorInterface $negotiator */
        $negotiator = $this->createMock(NegotiatorInterface::class);
        $negotiator->expects($this->never())
            ->method('handshake');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn(new ArrayAttributeStore());

        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->expectException(MissingRequest::class);

        new EstablishWebSocketConnection($decoratedMiddleware, $negotiator)->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened with an invalid request')]
    public function testOnOpenWithInvalidRequest(): void
    {
        /** @var Stub&RequestInterface $request */
        $request = $this->createStub(RequestInterface::class);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.request', $request);

        /** @var MockObject&StreamInterface $stream */
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->once())
            ->method('__toString')
            ->willReturn('');

        /** @var MockObject&ResponseInterface $response */
        $response = $this->createMock(ResponseInterface::class);
        $response->expects($this->once())
            ->method('withoutHeader')
            ->with('X-Powered-By')
            ->willReturnSelf();

        $response->expects($this->once())
            ->method('getProtocolVersion')
            ->willReturn('1.1');

        $response->expects($this->exactly(2))
            ->method('getStatusCode')
            ->willReturn(400);

        $response->expects($this->once())
            ->method('getReasonPhrase')
            ->willReturn('Bad Request');

        $response->expects($this->once())
            ->method('getHeaders')
            ->willReturn([]);

        $response->expects($this->once())
            ->method('getBody')
            ->willReturn($stream);

        /** @var MockObject&NegotiatorInterface $negotiator */
        $negotiator = $this->createMock(NegotiatorInterface::class);
        $negotiator->expects($this->once())
            ->method('handshake')
            ->with($request)
            ->willReturn($response);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('close');

        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        new EstablishWebSocketConnection($decoratedMiddleware, $negotiator)->onOpen($connection);

        $this->assertTrue($attributeStore->has('websocket.closing'));
        $this->assertFalse($attributeStore->get('websocket.closing'));
    }

    #[TestDox('Forwards the non-decorated connection to middleware onError if a decorator is not available')]
    public function testCanForwardNotDecoratedConnectionToMiddlewareOnError(): void
    {
        /** @var Stub&Connection $connection */
        $connection = $this->createStub(Connection::class);

        $exception = new \RuntimeException('Testing');

        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        new EstablishWebSocketConnection($decoratedMiddleware, $this->createStub(NegotiatorInterface::class))->onError($connection, $exception);
    }

    public function testTogglesStrictSubProtocolChecks(): void
    {
        /** @var MockObject&NegotiatorInterface $negotiator */
        $negotiator = $this->createMock(NegotiatorInterface::class);

        // The constructor call setStrictSubProtocolCheck to enable strict checks by default
        $negotiator->expects($this->exactly(2))
            ->method('setStrictSubProtocolCheck')
            ->withParameterSetsInOrder([true], [false]);

        new EstablishWebSocketConnection($this->createStub(ServerMiddleware::class), $negotiator)->setStrictSubProtocolCheck(false);
    }

    public function testEnableKeepAlive(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with(60, $this->isCallable())
            ->willReturn($this->createStub(TimerInterface::class));

        new EstablishWebSocketConnection($this->createStub(ServerMiddleware::class), $this->createStub(NegotiatorInterface::class))->enableKeepAlive($loop, 60);
    }

    #[TestDox('Replaces the keepalive timer when the keepalive is enabled again')]
    public function testEnableKeepAliveReplacesTimer(): void
    {
        $firstTimer = $this->createStub(TimerInterface::class);
        $secondTimer = $this->createStub(TimerInterface::class);

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->exactly(2))
            ->method('addPeriodicTimer')
            ->willReturn($firstTimer, $secondTimer);

        $loop->expects($this->once())
            ->method('cancelTimer')
            ->with($firstTimer);

        $middleware = new EstablishWebSocketConnection($this->createStub(ServerMiddleware::class), $this->createStub(NegotiatorInterface::class));
        $middleware->enableKeepAlive($loop, 30);
        $middleware->enableKeepAlive($loop, 60);
    }

    #[TestDox('Closes connections which do not respond to the keepalive ping')]
    public function testKeepAliveClosesUnresponsiveConnection(): void
    {
        [$middleware, $sendPings] = $this->createMiddlewareWithKeepAlive();

        $connection = $this->openConnection($middleware);

        $sendPings();

        $this->assertSame(Frame::OP_PING, $this->getOpcode($this->lastSent($connection)));
        $this->assertSame(0, $connection->closeCount);

        $sendPings();

        $this->assertSame(Frame::OP_CLOSE, $this->getOpcode($this->lastSent($connection)));
        $this->assertSame(1, $connection->closeCount);
    }

    #[TestDox('Keeps connections which respond to the keepalive ping')]
    public function testKeepAliveKeepsResponsiveConnection(): void
    {
        [$middleware, $sendPings] = $this->createMiddlewareWithKeepAlive();

        $connection = $this->openConnection($middleware);

        $sendPings();

        $pong = new Frame($this->getPayload($this->lastSent($connection)), true, Frame::OP_PONG);
        $pong->maskPayload();

        $middleware->onMessage($connection, $pong->getContents());

        $sendPings();

        $this->assertSame(Frame::OP_PING, $this->getOpcode($this->lastSent($connection)));
        $this->assertSame(0, $connection->closeCount);
    }

    #[TestDox('Does not ping or close connections which were closed after the last keepalive ping')]
    public function testKeepAliveForgetsClosedConnection(): void
    {
        [$middleware, $sendPings] = $this->createMiddlewareWithKeepAlive();

        $connection = $this->openConnection($middleware);

        $sendPings();

        $middleware->onClose($connection);

        $sentBeforeNextPing = \count($connection->sent);

        $sendPings();

        $this->assertCount($sentBeforeNextPing, $connection->sent, 'Nothing should be sent to a closed connection.');
        $this->assertSame(0, $connection->closeCount);
    }

    /**
     * @return \Generator<string, array{int|null, int|null, string}>
     */
    public static function dataOversizedMessage(): \Generator
    {
        yield 'Frame over the frame size limit' => [null, 16, 'Maximum frame size exceeded'];

        yield 'Message over the message size limit' => [16, null, 'Maximum message size exceeded'];
    }

    /**
     * @param int<0, max>|null $maxMessagePayloadSize
     * @param int<0, max>|null $maxFramePayloadSize
     */
    #[TestDox('Closes the connection with a "1009 Message Too Big" close frame when a message exceeds the size limits')]
    #[DataProvider('dataOversizedMessage')]
    public function testClosesConnectionForOversizedMessage(?int $maxMessagePayloadSize, ?int $maxFramePayloadSize, string $expectedReason): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects($this->never())
            ->method('onMessage');

        $negotiator = $this->createStub(NegotiatorInterface::class);
        $negotiator->method('handshake')
            ->willReturn(new Response(101, ['Upgrade' => 'websocket', 'Connection' => 'Upgrade']));

        $middleware = new EstablishWebSocketConnection($decoratedMiddleware, $negotiator, $maxMessagePayloadSize, $maxFramePayloadSize);

        $connection = $this->openConnection($middleware);

        $frame = new Frame(str_repeat('a', 32));
        $frame->maskPayload();

        $middleware->onMessage($connection, $frame->getContents());

        $closeFrame = $this->lastSent($connection);

        $this->assertSame(Frame::OP_CLOSE, $this->getOpcode($closeFrame));
        $this->assertSame(Frame::CLOSE_TOO_BIG, unpack('n', substr($this->getPayload($closeFrame), 0, 2))[1] ?? null);
        $this->assertStringContainsString($expectedReason, $this->getPayload($closeFrame));
        $this->assertSame(1, $connection->closeCount);
    }

    #[TestDox('Accepts a message within the size limits')]
    public function testAcceptsMessageWithinSizeLimits(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($this->isInstanceOf(WebSocketConnection::class), str_repeat('a', 16));

        $negotiator = $this->createStub(NegotiatorInterface::class);
        $negotiator->method('handshake')
            ->willReturn(new Response(101, ['Upgrade' => 'websocket', 'Connection' => 'Upgrade']));

        $middleware = new EstablishWebSocketConnection($decoratedMiddleware, $negotiator, 16, 16);

        $connection = $this->openConnection($middleware);

        $frame = new Frame(str_repeat('a', 16));
        $frame->maskPayload();

        $middleware->onMessage($connection, $frame->getContents());

        $this->assertSame(0, $connection->closeCount);
    }

    #[TestDox('Closes all established connections with a "1001 Going Away" close frame')]
    public function testClosesAllConnections(): void
    {
        $negotiator = $this->createStub(NegotiatorInterface::class);
        $negotiator->method('handshake')
            ->willReturn(new Response(101, ['Upgrade' => 'websocket', 'Connection' => 'Upgrade']));

        $middleware = new EstablishWebSocketConnection($this->createStub(ServerMiddleware::class), $negotiator);

        $connections = [$this->openConnection($middleware), $this->openConnection($middleware)];

        $middleware->closeAllConnections();

        foreach ($connections as $connection) {
            $closeFrame = $this->lastSent($connection);

            $this->assertSame(Frame::OP_CLOSE, $this->getOpcode($closeFrame));
            $this->assertSame(Frame::CLOSE_GOING_AWAY, unpack('n', $this->getPayload($closeFrame))[1] ?? null);
            $this->assertSame(1, $connection->closeCount);
        }
    }

    /**
     * @return array{EstablishWebSocketConnection, callable(): void}
     */
    private function createMiddlewareWithKeepAlive(): array
    {
        $sendPings = null;

        $loop = $this->createStub(LoopInterface::class);
        $loop->method('addPeriodicTimer')
            ->willReturnCallback(function (float|int $interval, callable $callback) use (&$sendPings): TimerInterface {
                $sendPings = $callback;

                return $this->createStub(TimerInterface::class);
            });

        // The handshake is stubbed as these tests only cover the keepalive behavior of established connections
        $negotiator = $this->createStub(NegotiatorInterface::class);
        $negotiator->method('handshake')
            ->willReturn(new Response(101, ['Upgrade' => 'websocket', 'Connection' => 'Upgrade']));

        $middleware = new EstablishWebSocketConnection($this->createStub(ServerMiddleware::class), $negotiator);
        $middleware->enableKeepAlive($loop);

        $this->assertIsCallable($sendPings);

        return [$middleware, $sendPings];
    }

    private function openConnection(EstablishWebSocketConnection $middleware): RecordingConnection
    {
        $connection = new RecordingConnection();
        $connection->getAttributeStore()->set('http.request', $this->createStub(RequestInterface::class));

        $middleware->onOpen($connection);

        $this->assertStringStartsWith('HTTP/1.1 101 ', $connection->sent[0] ?? '', 'The WebSocket handshake should succeed.');

        return $connection;
    }

    private function lastSent(RecordingConnection $connection): string
    {
        $this->assertNotSame([], $connection->sent);

        return $connection->sent[array_key_last($connection->sent)];
    }

    private function getOpcode(string $frame): int
    {
        return \ord($frame[0]) & 0x0F;
    }

    /**
     * Extracts the payload from an unmasked server frame with a payload shorter than 126 bytes.
     */
    private function getPayload(string $frame): string
    {
        return substr($frame, 2, \ord($frame[1]) & 0x7F);
    }
}
