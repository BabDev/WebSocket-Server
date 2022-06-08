<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\ServerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RejectBlockedIpAddressTest extends TestCase
{
    private MockObject & ServerMiddleware $decoratedMiddleware;
    private RejectBlockedIpAddress $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $this->middleware = new RejectBlockedIpAddress($this->decoratedMiddleware);
    }

    /**
     * @testdox Handles a new connection being opened with no remote address
     */
    public function testOnOpenWithNoRemoteAddress(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn(null);

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
     * @testdox Handles a new connection being opened with no blocked addresses
     */
    public function testOnOpenWithNoBlockedAddresses(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('192.168.1.1');

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
     * @testdox Handles a new connection being opened with blocked addresses
     */
    public function testOnOpenWithBlockedAddresses(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('192.168.1.1');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->blockAddress('127.0.0.1');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles a new connection being opened when the remote address is blocked
     */
    public function testOnOpenWithBlockedRemoteAddress(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('192.168.1.1');

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

        $this->middleware->blockAddress('192.168.1.1');

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles incoming data on the connection
     */
    public function testOnMessage(): void
    {
        $message = 'Testing';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('192.168.1.1');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

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
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('192.168.1.1');

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

    /**
     * @testdox Handles an error
     */
    public function testOnError(): void
    {
        $exception = new \RuntimeException('Testing');

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('192.168.1.1');

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

    public function testManagesBlockedAddressList(): void
    {
        $blockedAddresses = (new \ReflectionClass($this->middleware))->getProperty('blockedAddresses');

        $this->middleware->blockAddress('192.168.1.1');
        $this->middleware->blockAddress('192.168.0.0/24');

        $this->assertTrue(\in_array('192.168.1.1', $blockedAddresses->getValue($this->middleware), true));
        $this->assertTrue(\in_array('192.168.0.0/24', $blockedAddresses->getValue($this->middleware), true));

        $this->middleware->allowAddress('192.168.1.1');

        $this->assertFalse(\in_array('192.168.1.1', $blockedAddresses->getValue($this->middleware), true));
    }
}
