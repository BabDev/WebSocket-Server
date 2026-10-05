<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\ServerMiddleware;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class RejectBlockedIpAddressTest extends TestCase
{
    #[TestDox('Handles a new connection being opened with no remote address')]
    public function testOnOpenWithNoRemoteAddress(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened with no blocked addresses')]
    public function testOnOpenWithNoBlockedAddresses(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened with blocked addresses')]
    public function testOnOpenWithBlockedAddresses(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $middleware->blockAddress('127.0.0.1');

        $middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened when the remote address is blocked')]
    public function testOnOpenWithBlockedRemoteAddress(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $middleware->blockAddress('192.168.1.1');

        $middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened when the remote IPv6 address is in a blocked subnet')]
    public function testOnOpenWithBlockedRemoteIpv6Address(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('remote_address')
            ->willReturn('2001:db8::1');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $middleware->blockAddress('2001:db8::/32');

        $middleware->onOpen($connection);
    }

    #[TestDox('Handles incoming data on the connection')]
    public function testOnMessage(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $message);

        $middleware->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection')]
    public function testOnClose(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $middleware->onClose($connection);
    }

    #[TestDox('Handles an error')]
    public function testOnError(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $middleware = $this->createMiddleware($decoratedMiddleware);

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

        $decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $middleware->onError($connection, $exception);
    }

    public function testManagesBlockedAddressList(): void
    {
        $middleware = $this->createMiddleware();

        $blockedAddresses = new \ReflectionClass($middleware)->getProperty('blockedAddresses');

        $middleware->blockAddress('192.168.1.1');
        $middleware->blockAddress('192.168.0.0/24');

        /** @var list<non-empty-string> $addresses */
        $addresses = $blockedAddresses->getValue($middleware);

        $this->assertContains('192.168.1.1', $addresses);
        $this->assertContains('192.168.0.0/24', $addresses);

        $middleware->allowAddress('192.168.1.1');

        /** @var list<non-empty-string> $addresses */
        $addresses = $blockedAddresses->getValue($middleware);

        $this->assertNotContains('192.168.1.1', $addresses);
        $this->assertContains('192.168.0.0/24', $addresses);
    }

    /**
     * @param list<non-empty-string> $blockedAddresses
     */
    private function createMiddleware(?ServerMiddleware $decoratedMiddleware = null, array $blockedAddresses = []): RejectBlockedIpAddress
    {
        return new RejectBlockedIpAddress($decoratedMiddleware ?? $this->createStub(ServerMiddleware::class), $blockedAddresses);
    }
}
