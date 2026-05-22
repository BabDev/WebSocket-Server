<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WebSocket\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\StreamInterface;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

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
            ->willReturnMap([
                [true],
                [false],
            ]);

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
}
