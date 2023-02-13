<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WebSocket\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class EstablishWebSocketConnectionTest extends TestCase
{
    private MockObject&ServerMiddleware $decoratedMiddleware;

    private MockObject&NegotiatorInterface $negotiator;

    private EstablishWebSocketConnection $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $this->negotiator = $this->createMock(NegotiatorInterface::class);

        $this->middleware = new EstablishWebSocketConnection($this->decoratedMiddleware, $this->negotiator);
    }

    /**
     * @testdox Handles activity during the lifecycle of a connection
     */
    public function testConnectionLifecycle(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.request', $request);

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
            ->willReturn('');

        $this->negotiator->expects($this->once())
            ->method('handshake')
            ->with($request)
            ->willReturn($response);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($this->isInstanceOf(WebSocketConnection::class));

        $this->middleware->onOpen($connection);

        $this->middleware->onMessage($connection, 'Incoming');

        $exception = new \RuntimeException('Testing');

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($this->isInstanceOf(WebSocketConnection::class), $exception);

        $this->middleware->onError($connection, $exception);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($this->isInstanceOf(WebSocketConnection::class));

        $this->middleware->onClose($connection);
    }

    /**
     * @testdox Handles a new connection being opened when required middleware have not run before this middleware
     */
    public function testOnOpenWithoutRequest(): void
    {
        $this->expectException(MissingRequest::class);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn(null);

        $this->negotiator->expects($this->never())
            ->method('handshake');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles a new connection being opened with an invalid request
     */
    public function testOnOpenWithInvalidRequest(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('websocket.closing', false);

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
            ->willReturn('');

        $this->negotiator->expects($this->once())
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

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->onOpen($connection);
    }

    public function testTogglesStrictSubProtocolChecks(): void
    {
        $this->negotiator->expects($this->once())
            ->method('setStrictSubProtocolCheck')
            ->with(false);

        $this->middleware->setStrictSubProtocolCheck(false);
    }

    public function testEnableKeepAlive(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with(60, $this->isType('callable'))
            ->willReturn($this->createMock(TimerInterface::class));

        $this->middleware->enableKeepAlive($loop, 60);
    }
}
