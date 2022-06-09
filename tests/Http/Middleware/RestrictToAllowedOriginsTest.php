<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\ServerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class RestrictToAllowedOriginsTest extends TestCase
{
    private MockObject & ServerMiddleware $decoratedMiddleware;
    private RestrictToAllowedOrigins $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $this->middleware = new RestrictToAllowedOrigins($this->decoratedMiddleware);
    }

    /**
     * @testdox Handles a new connection being opened with no origin restrictions
     */
    public function testOnOpenWithNoOriginRestrictions(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles a new connection being opened with restricted origins and no Origin header
     */
    public function testOnOpenWithRestrictedOriginsAndNoOriginHeader(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Origin')
            ->willReturn(false);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->allowOrigin('localhost');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles a new connection being opened with restricted origins and a Origin header with an allowed origin
     */
    public function testOnOpenWithRestrictedOriginsAndOriginHeaderWithAllowedOrigin(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Origin')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('getHeader')
            ->with('Origin')
            ->willReturn(['http://localhost']);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->allowOrigin('localhost');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles a new connection being opened with restricted origins and a Origin header with a disallowed origin
     */
    public function testOnOpenWithRestrictedOriginsAndOriginHeaderWithDisallowedOrigin(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Origin')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('getHeader')
            ->with('Origin')
            ->willReturn(['https://www.babdev.com']);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->allowOrigin('localhost');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles a new connection being opened with restricted origins and a malformed Origin header
     */
    public function testOnOpenWithRestrictedOriginsAndMalformedOriginHeader(): void
    {
        $this->expectException(MalformedRequest::class);

        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Origin')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('getHeader')
            ->with('Origin')
            ->willReturn(['https:/wwwbabdevcom']);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->allowOrigin('localhost');

        $this->middleware->onOpen($connection);
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

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles incoming data on the connection
     */
    public function testOnMessage(): void
    {
        $message = 'Testing';

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $message);

        $this->middleware->onMessage($connection, $message);
    }

    /**
     * @testdox Closes the connection
     */
    public function testOnClose(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->middleware->onClose($connection);
    }

    /**
     * @testdox Handles an error
     */
    public function testOnError(): void
    {
        $exception = new \RuntimeException('Testing');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $this->middleware->onError($connection, $exception);
    }

    public function testManagesAllowedOriginList(): void
    {
        $allowedOrigins = (new \ReflectionClass($this->middleware))->getProperty('allowedOrigins');

        $this->middleware->allowOrigin('192.168.1.1');
        $this->middleware->allowOrigin('localhost');

        $this->assertTrue(\in_array('192.168.1.1', $allowedOrigins->getValue($this->middleware), true));
        $this->assertTrue(\in_array('localhost', $allowedOrigins->getValue($this->middleware), true));

        $this->middleware->removeAllowedOrigin('192.168.1.1');

        $this->assertFalse(\in_array('192.168.1.1', $allowedOrigins->getValue($this->middleware), true));
    }
}
