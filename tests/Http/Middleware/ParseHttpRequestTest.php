<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Http\Exception\MessageTooLarge;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\ServerMiddleware;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ParseHttpRequestTest extends TestCase
{
    private MockObject&ServerMiddleware $decoratedMiddleware;
    private MockObject&RequestParser $requestParser;
    private ParseHttpRequest $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $this->requestParser = $this->createMock(RequestParser::class);

        $this->middleware = new ParseHttpRequest($this->decoratedMiddleware, $this->requestParser);
    }

    #[TestDox('Handles a new connection being opened')]
    public function testOnOpen(): void
    {
        $attributeStore = new ArrayAttributeStore();

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->middleware->onOpen($connection);

        $this->assertFalse($attributeStore->get('http.headers_received'));
    }

    #[TestDox('Handles incoming data on the connection when the HTTP message has not yet been parsed')]
    public function testOnMessageWhenHttpMessageNotYetParsed(): void
    {
        $message = 'Testing';

        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->requestParser->expects($this->once())
            ->method('parse')
            ->with($connection, $message)
            ->willReturn($request);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->onMessage($connection, $message);

        $this->assertTrue($attributeStore->get('http.headers_received'));
        $this->assertSame($request, $attributeStore->get('http.request'));
    }

    #[TestDox('Handles incoming data on the connection when the HTTP message has been parsed')]
    public function testOnMessageWhenHttpMessageHasBeenParsed(): void
    {
        $message = 'Testing';

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', true);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->requestParser->expects($this->never())
            ->method('parse');

        $this->decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $message);

        $this->middleware->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection when a buffer overflow is reached while processing incoming data')]
    public function testOnMessageWhenHttpMessageOverflows(): void
    {
        $message = 'Testing';

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $this->requestParser->expects($this->once())
            ->method('parse')
            ->with($connection, $message)
            ->willThrowException(new MessageTooLarge('Testing'));

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection when the request has been parsed')]
    public function testOnCloseWhenRequestParsed(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', true);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->middleware->onClose($connection);
    }

    #[TestDox('Handles an error when the request has been parsed')]
    public function testOnErrorWhenRequestParsed(): void
    {
        $exception = new \RuntimeException('Testing');

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', true);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $this->middleware->onError($connection, $exception);
    }

    #[TestDox('Handles an error when the request has not been parsed')]
    public function testOnErrorWhenRequestNotParsed(): void
    {
        $exception = new \RuntimeException('Testing');

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onError');

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $this->middleware->onError($connection, $exception);
    }
}
