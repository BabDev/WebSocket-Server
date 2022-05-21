<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Component;

use BabDev\WebSocket\Server\Component\ParseHttpRequest;
use BabDev\WebSocket\Server\Connection\AttributeStoreInterface;
use BabDev\WebSocket\Server\ConnectionInterface;
use BabDev\WebSocket\Server\Http\RequestParserInterface;
use BabDev\WebSocket\Server\RequestAwareServerComponentInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class ParseHttpRequestTest extends TestCase
{
    private MockObject&RequestAwareServerComponentInterface $decoratedComponent;
    private MockObject&RequestParserInterface $requestParser;
    private ParseHttpRequest $component;

    protected function setUp(): void
    {
        $this->decoratedComponent = $this->createMock(RequestAwareServerComponentInterface::class);
        $this->requestParser = $this->createMock(RequestParserInterface::class);

        $this->component = new ParseHttpRequest($this->decoratedComponent, $this->requestParser);
    }

    /**
     * @testdox Handles a new connection being opened
     */
    public function testOnOpen(): void
    {
        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('set')
            ->with('http.headers_received', false);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->component->onOpen($connection);
    }

    /**
     * @testdox Handles incoming data on the connection when the HTTP message has not yet been parsed
     */
    public function testOnMessageWhenHttpMessageNotYetParsed(): void
    {
        $message = 'Testing';

        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);

        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.headers_received')
            ->willReturn(false);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('http.headers_received', true);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->exactly(2))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->requestParser->expects($this->once())
            ->method('parse')
            ->with($connection, $message)
            ->willReturn($request);

        $this->decoratedComponent->expects($this->once())
            ->method('onOpen')
            ->with($connection, $request);

        $this->component->onMessage($connection, $message);
    }

    /**
     * @testdox Handles incoming data on the connection when the HTTP message has been parsed
     */
    public function testOnMessageWhenHttpMessageHasBeenParsed(): void
    {
        $message = 'Testing';

        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.headers_received')
            ->willReturn(true);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->requestParser->expects($this->never())
            ->method('parse');

        $this->decoratedComponent->expects($this->once())
            ->method('onMessage')
            ->with($connection, $message);

        $this->component->onMessage($connection, $message);
    }

    /**
     * @testdox Closes the connection when processing incoming data and it causes a buffer overflow
     */
    public function testOnMessageWhenHttpMessageOverflows(): void
    {
        $message = 'Testing';

        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.headers_received')
            ->willReturn(false);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
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
            ->willThrowException(new \OverflowException('Testing'));

        $this->decoratedComponent->expects($this->never())
            ->method('onOpen');

        $this->component->onMessage($connection, $message);
    }

    /**
     * @testdox Closes the connection when the request has been parsed
     */
    public function testOnCloseWhenRequestParsed(): void
    {
        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.headers_received')
            ->willReturn(true);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedComponent->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->component->onClose($connection);
    }

    /**
     * @testdox Handles an error when the request has been parsed
     */
    public function testOnErrorWhenRequestParsed(): void
    {
        $exception = new \RuntimeException('Testing');

        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.headers_received')
            ->willReturn(true);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedComponent->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $this->component->onError($connection, $exception);
    }

    /**
     * @testdox Handles an error when the request has not been parsed
     */
    public function testOnErrorWhenRequestNotParsed(): void
    {
        $exception = new \RuntimeException('Testing');

        /** @var MockObject&AttributeStoreInterface $attributeStore */
        $attributeStore = $this->createMock(AttributeStoreInterface::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.headers_received')
            ->willReturn(false);

        /** @var MockObject&ConnectionInterface $connection */
        $connection = $this->createMock(ConnectionInterface::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedComponent->expects($this->never())
            ->method('onError');

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $this->component->onError($connection, $exception);
    }
}
