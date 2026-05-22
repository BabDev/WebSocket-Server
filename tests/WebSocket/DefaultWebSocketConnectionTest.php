<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WebSocket;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\WebSocket\DefaultWebSocketConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Messaging\DataInterface;

final class DefaultWebSocketConnectionTest extends TestCase
{
    public function testProvidesTheAttributeStoreFromTheDecoratedConnection(): void
    {
        /** @var Stub&AttributeStore $attributeStore */
        $attributeStore = $this->createStub(AttributeStore::class);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame(
            $attributeStore,
            new DefaultWebSocketConnection($decoratedConnection)->getAttributeStore(),
        );
    }

    public function testProvidesTheDecoratedConnection(): void
    {
        /** @var Stub&Connection $decoratedConnection */
        $decoratedConnection = $this->createStub(Connection::class);

        $this->assertSame(
            $decoratedConnection,
            new DefaultWebSocketConnection($decoratedConnection)->getConnection(),
        );
    }

    public function testSendsAMessageWhenTheWebsocketStateIsNotClosing(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('websocket.closing', false);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->once())
            ->method('send');

        new DefaultWebSocketConnection($decoratedConnection)->send('Hello World!');
    }

    public function testDoesNotSendAMessageWhenTheWebsocketStateIsClosing(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('websocket.closing', true);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->never())
            ->method('send');

        new DefaultWebSocketConnection($decoratedConnection)->send('Hello World!');
    }

    public function testClosesAConnectionWithACode(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('websocket.closing', false);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->once())
            ->method('send');

        $decoratedConnection->expects($this->once())
            ->method('close');

        new DefaultWebSocketConnection($decoratedConnection)->close(1000);

        $this->assertTrue($attributeStore->get('websocket.closing'));
    }

    public function testClosesAConnectionWithADataObject(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('websocket.closing', false);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->once())
            ->method('send');

        $decoratedConnection->expects($this->once())
            ->method('close');

        /** @var MockObject&DataInterface $data */
        $data = $this->createMock(DataInterface::class);
        $data->expects($this->once())
            ->method('getContents')
            ->willReturn('Signing off!');

        new DefaultWebSocketConnection($decoratedConnection)->close($data);

        $this->assertTrue($attributeStore->get('websocket.closing'));
    }

    public function testClosesAConnectionWithoutSendingAMessageWhenTheWebsocketStateIsClosing(): void
    {
        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('websocket.closing', true);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->never())
            ->method('send');

        $decoratedConnection->expects($this->never())
            ->method('close');

        new DefaultWebSocketConnection($decoratedConnection)->close(1000);
    }
}
