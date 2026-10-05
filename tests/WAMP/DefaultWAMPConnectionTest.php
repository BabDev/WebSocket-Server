<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\WAMP\DefaultWAMPConnection;
use BabDev\WebSocket\Server\WAMP\MessageType;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;

#[AllowMockObjectsWithoutExpectations]
final class DefaultWAMPConnectionTest extends TestCase
{
    private readonly MockObject&Connection $decoratedConnection;

    private readonly DefaultWAMPConnection $connection;

    protected function setUp(): void
    {
        $this->decoratedConnection = $this->createMock(Connection::class);

        $this->connection = new DefaultWAMPConnection($this->decoratedConnection);
    }

    public function testProvidesTheAttributeStoreFromTheDecoratedConnection(): void
    {
        /** @var Stub&AttributeStore $attributeStore */
        $attributeStore = $this->createStub(AttributeStore::class);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame($attributeStore, $this->connection->getAttributeStore());
    }

    public function testProvidesTheDecoratedConnection(): void
    {
        $this->assertSame($this->decoratedConnection, $this->connection->getConnection());
    }

    public function testSendsAMessage(): void
    {
        $message = 'Testing';

        $this->decoratedConnection->expects($this->once())
            ->method('send')
            ->with($message);

        $this->connection->send('Testing');
    }

    public function testClosesAConnection(): void
    {
        $data = 'Close';

        $this->decoratedConnection->expects($this->once())
            ->method('close')
            ->with($data);

        $this->connection->close($data);
    }

    public function testSendsACallResultMessage(): void
    {
        $callId = uniqid();
        $data = ['hello' => 'world'];

        $this->decoratedConnection->expects($this->once())
            ->method('send')
            ->with(json_encode([MessageType::CALL_RESULT, $callId, $data], \JSON_THROW_ON_ERROR));

        $this->connection->callResult($callId, $data);
    }

    public function testSendsACallErrorMessage(): void
    {
        $callId = uniqid();
        $uri = 'https://example.com/error#testing';

        $this->decoratedConnection->expects($this->once())
            ->method('send')
            ->with(json_encode([MessageType::CALL_ERROR, $callId, $uri, 'Testing Error'], \JSON_THROW_ON_ERROR));

        $this->connection->callError($callId, $uri, 'Testing Error');
    }

    public function testSendsACallErrorMessageWithDetails(): void
    {
        $callId = uniqid();
        $uri = 'https://example.com/error#testing';
        $details = 'Testing';

        $this->decoratedConnection->expects($this->once())
            ->method('send')
            ->with(json_encode([MessageType::CALL_ERROR, $callId, $uri, 'Testing Error', $details], \JSON_THROW_ON_ERROR));

        $this->connection->callError($callId, $uri, 'Testing Error', $details);
    }

    public function testSendsAnEventMessage(): void
    {
        $uri = 'https://example.com/testing';
        $data = ['hello' => 'world'];

        $this->decoratedConnection->expects($this->once())
            ->method('send')
            ->with(json_encode([MessageType::EVENT, $uri, $data]));

        $this->connection->event($uri, $data);
    }

    public function testSendsAPrefixMessage(): void
    {
        $prefix = 'testing';
        $uri = 'https://example.com/testing';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('wamp.prefixes', [])
            ->willReturn([]);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('wamp.prefixes', [$prefix => $uri]);

        $this->decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedConnection->expects($this->once())
            ->method('send')
            ->with(json_encode([MessageType::PREFIX, $prefix, $uri]));

        $this->connection->prefix($prefix, $uri);
    }

    public function testGetUriWithNonHttpProtocol(): void
    {
        $uri = 'ssh://example.com/testing';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('wamp.prefixes', [])
            ->willReturn([]);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame($uri, $this->connection->getUri($uri));
    }

    public function testGetUriWithoutCurieSeparator(): void
    {
        $uri = 'example.com/testing';

        $this->assertSame($uri, $this->connection->getUri($uri));
    }

    public function testGetUriWithHttpProtocol(): void
    {
        $uri = 'https://example.com/testing';

        $this->assertSame($uri, $this->connection->getUri($uri));
    }

    public function testGetUriWithCurieAndUnregisteredPrefix(): void
    {
        $uri = 'testing:curie';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('wamp.prefixes', [])
            ->willReturn([]);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame($uri, $this->connection->getUri($uri));
    }

    public function testGetUriWithCurieAndRegisteredPrefix(): void
    {
        $prefix = 'testing';
        $uri = 'https://example.com/testing#';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('wamp.prefixes', [])
            ->willReturn([$prefix => $uri]);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame('https://example.com/testing#curie', $this->connection->getUri('testing:curie'));
    }

    /**
     * @return \Generator<string, array{string, string}>
     */
    public static function dataUriResolution(): \Generator
    {
        yield 'WAMP v1 specification example' => ['calc:square', 'http://example.com/simple/calc#square'];

        yield 'Reference containing the CURIE separator' => ['calc:square:extra', 'http://example.com/simple/calc#square:extra'];

        yield 'Full HTTP URI' => ['http://example.com/simple/calc#square', 'http://example.com/simple/calc#square'];

        yield 'Full HTTPS URI with an uppercase scheme' => ['HTTPS://example.com/simple/calc#square', 'HTTPS://example.com/simple/calc#square'];

        yield 'CURIE whose reference contains an HTTP URI' => ['calc:http://example.com', 'http://example.com/simple/calc#http://example.com'];

        yield 'Unregistered prefix' => ['other:square', 'other:square'];
    }

    #[TestDox('Resolves URIs and CURIEs using the registered prefixes')]
    #[DataProvider('dataUriResolution')]
    public function testUriResolution(string $uri, string $expected): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('wamp.prefixes', ['calc' => 'http://example.com/simple/calc#']);

        $this->decoratedConnection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame($expected, $this->connection->getUri($uri));
    }
}
