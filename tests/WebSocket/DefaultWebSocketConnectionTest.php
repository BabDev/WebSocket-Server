<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WebSocket;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\WebSocket\DefaultWebSocketConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Ratchet\RFC6455\Messaging\DataInterface;

final class DefaultWebSocketConnectionTest extends TestCase
{
    private MockObject&Connection $decoratedConnection;

    private DefaultWebSocketConnection $connection;

    protected function setUp(): void
    {
        $this->decoratedConnection = $this->createMock(Connection::class);

        $this->connection = new DefaultWebSocketConnection($this->decoratedConnection);
    }

    public function testProvidesTheAttributeStoreFromTheDecoratedConnection(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->assertSame($attributeStore, $this->connection->getAttributeStore());
    }

    public function testProvidesTheDecoratedConnection(): void
    {
        $this->assertSame($this->decoratedConnection, $this->connection->getConnection());
    }

    public function testSendsAMessageWhenTheWebsocketStateIsNotClosing(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('websocket.closing', false)
            ->willReturn(false);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $message = 'Hello World!';

        $this->decoratedConnection->expects($this->once())
            ->method('send');

        $this->connection->send($message);
    }

    public function testDoesNotSendAMessageWhenTheWebsocketStateIsClosing(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('websocket.closing', false)
            ->willReturn(true);

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $message = 'Hello World!';

        $this->decoratedConnection->expects($this->never())
            ->method('send');

        $this->connection->send($message);
    }

    public function testClosesAConnectionWithACode(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->atLeastOnce())
            ->method('get')
            ->with('websocket.closing', false)
            ->willReturn(false);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('websocket.closing', true);

        $this->decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedConnection->expects($this->once())
            ->method('send');

        $this->decoratedConnection->expects($this->once())
            ->method('close');

        $this->connection->close(1000);
    }

    public function testClosesAConnectionWithADataObject(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->atLeastOnce())
            ->method('get')
            ->with('websocket.closing', false)
            ->willReturn(false);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('websocket.closing', true);

        $this->decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedConnection->expects($this->once())
            ->method('send');

        $this->decoratedConnection->expects($this->once())
            ->method('close');

        /** @var MockObject&DataInterface $data */
        $data = $this->createMock(DataInterface::class);
        $data->expects($this->once())
            ->method('getContents')
            ->willReturn('Signing off!');

        $this->connection->close($data);
    }

    public function testClosesAConnectionWithoutSendingAMessageWhenTheWebsocketStateIsClosing(): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('websocket.closing', false)
            ->willReturn(true);

        $attributeStore->expects($this->never())
            ->method('set');

        $this->decoratedConnection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedConnection->expects($this->never())
            ->method('send');

        $this->decoratedConnection->expects($this->never())
            ->method('close');

        $this->connection->close();
    }
}
