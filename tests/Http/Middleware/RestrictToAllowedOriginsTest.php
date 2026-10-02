<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\Exception\InvalidAllowedOrigin;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Tests\Fixtures\RecordingConnection;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class RestrictToAllowedOriginsTest extends TestCase
{
    private readonly MockObject&ServerMiddleware $decoratedMiddleware;
    private readonly RestrictToAllowedOrigins $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $this->middleware = new RestrictToAllowedOrigins($this->decoratedMiddleware);
    }

    #[TestDox('Handles a new connection being opened with no origin restrictions')]
    public function testOnOpenWithNoOriginRestrictions(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createStub(RequestInterface::class);

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

    #[TestDox('Handles a new connection being opened with restricted origins and no Origin header')]
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

    #[TestDox('Handles a new connection being opened with restricted origins and a Origin header with an allowed origin')]
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

    #[TestDox('Handles a new connection being opened with restricted origins and a Origin header with a disallowed origin')]
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

    #[TestDox('Handles a new connection being opened with restricted origins and a malformed Origin header')]
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

    #[TestDox('Handles a new connection being opened when required middleware have not run before this middleware')]
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

    #[TestDox('Handles incoming data on the connection')]
    public function testOnMessage(): void
    {
        $message = 'Testing';

        /** @var Stub&Connection $connection */
        $connection = $this->createStub(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $message);

        $this->middleware->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection')]
    public function testOnClose(): void
    {
        /** @var Stub&Connection $connection */
        $connection = $this->createStub(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->middleware->onClose($connection);
    }

    #[TestDox('Handles an error')]
    public function testOnError(): void
    {
        $exception = new \RuntimeException('Testing');

        /** @var Stub&Connection $connection */
        $connection = $this->createStub(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $this->middleware->onError($connection, $exception);
    }

    public function testManagesAllowedOriginList(): void
    {
        $allowedOrigins = new \ReflectionClass($this->middleware)->getProperty('allowedOrigins');

        $this->middleware->allowOrigin('192.168.1.1');
        $this->middleware->allowOrigin('localhost');

        /** @var list<non-empty-string> $origins */
        $origins = $allowedOrigins->getValue($this->middleware);

        $this->assertContains('192.168.1.1', $origins);
        $this->assertContains('localhost', $origins);

        $this->middleware->removeAllowedOrigin('192.168.1.1');

        /** @var list<non-empty-string> $origins */
        $origins = $allowedOrigins->getValue($this->middleware);

        $this->assertNotContains('192.168.1.1', $origins);
        $this->assertContains('localhost', $origins);
    }

    /**
     * @return \Generator<string, array{list<non-empty-string>, non-empty-string, bool}>
     */
    public static function dataOriginMatching(): \Generator
    {
        yield 'Host entry allows any scheme' => [['example.com'], 'http://example.com', true];

        yield 'Host entry allows any port' => [['example.com'], 'https://example.com:8443', true];

        yield 'Host entry is case-insensitive' => [['Example.COM'], 'https://example.com', true];

        yield 'Origin header host is case-insensitive' => [['example.com'], 'https://EXAMPLE.com', true];

        yield 'Host entry does not allow subdomains' => [['example.com'], 'https://evil.example.com', false];

        yield 'Host entry does not allow a suffixed domain' => [['example.com'], 'https://example.com.evil.test', false];

        yield 'Host entry for an IPv6 address' => [['[::1]'], 'http://[::1]:8080', true];

        yield 'Full origin entry allows the same origin' => [['https://example.com'], 'https://example.com', true];

        yield 'Full origin entry allows the explicit default port' => [['https://example.com'], 'https://example.com:443', true];

        yield 'Full origin entry with the explicit default port allows the implicit port' => [['https://example.com:443'], 'https://example.com', true];

        yield 'Full origin entry is case-insensitive' => [['HTTPS://Example.com'], 'https://example.COM', true];

        yield 'Full origin entry rejects another scheme' => [['https://example.com'], 'http://example.com', false];

        yield 'Full origin entry rejects another port' => [['https://example.com'], 'https://example.com:8443', false];

        yield 'Full origin entry with a port allows that port' => [['http://localhost:3000'], 'http://localhost:3000', true];

        yield 'Full origin entry with a port rejects the default port' => [['http://localhost:3000'], 'http://localhost', false];

        yield 'Full origin entry for a secure WebSocket origin' => [['wss://example.com'], 'wss://example.com:443', true];
    }

    /**
     * @param list<non-empty-string> $allowedOrigins
     * @param non-empty-string       $originHeader
     */
    #[TestDox('Matches the Origin header against the allowed origins')]
    #[DataProvider('dataOriginMatching')]
    public function testOriginMatching(array $allowedOrigins, string $originHeader, bool $expectedAllowed): void
    {
        $connection = new RecordingConnection();
        $connection->getAttributeStore()->set('http.request', new Request('GET', '/', ['Origin' => $originHeader]));

        $this->decoratedMiddleware->expects($expectedAllowed ? $this->once() : $this->never())
            ->method('onOpen')
            ->with($connection);

        new RestrictToAllowedOrigins($this->decoratedMiddleware, $allowedOrigins)->onOpen($connection);

        if ($expectedAllowed) {
            $this->assertSame([], $connection->sent);
            $this->assertSame(0, $connection->closeCount);
        } else {
            $this->assertCount(1, $connection->sent);
            $this->assertStringStartsWith('HTTP/1.1 403 ', $connection->sent[0]);
            $this->assertSame(1, $connection->closeCount);
        }
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function dataInvalidAllowedOrigin(): \Generator
    {
        yield 'Empty string' => [''];

        yield 'Host with a port' => ['localhost:8080'];

        yield 'Host with a path' => ['example.com/path'];

        yield 'Origin without a host' => ['https://'];

        yield 'Origin with a path' => ['https://example.com/path'];

        yield 'Origin with a query' => ['https://example.com?query=1'];

        yield 'Origin with credentials' => ['https://user@example.com'];
    }

    #[TestDox('Rejects an invalid allowed origin')]
    #[DataProvider('dataInvalidAllowedOrigin')]
    public function testRejectsInvalidAllowedOrigin(string $origin): void
    {
        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        try {
            $this->middleware->allowOrigin($origin); // @phpstan-ignore argument.type

            self::fail(\sprintf('A %s exception should have been thrown.', InvalidAllowedOrigin::class));
        } catch (InvalidAllowedOrigin $exception) {
            $this->assertSame($origin, $exception->origin);
        }
    }

    #[TestDox('Rejects an invalid allowed origin given to the constructor')]
    public function testRejectsInvalidAllowedOriginInConstructor(): void
    {
        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->expectException(InvalidAllowedOrigin::class);

        new RestrictToAllowedOrigins($this->decoratedMiddleware, ['localhost:8080']);
    }

    #[TestDox('Removes an allowed origin given in an equivalent form')]
    public function testRemovesEquivalentAllowedOrigin(): void
    {
        $connection = new RecordingConnection();
        $connection->getAttributeStore()->set('http.request', new Request('GET', '/', ['Origin' => 'https://example.com']));

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->allowOrigin('https://example.com');
        $this->middleware->allowOrigin('localhost');
        $this->middleware->removeAllowedOrigin('HTTPS://EXAMPLE.com:443');

        $this->middleware->onOpen($connection);

        $this->assertStringStartsWith('HTTP/1.1 403 ', $connection->sent[0] ?? '');
    }
}
