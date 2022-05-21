<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Connection;

use BabDev\WebSocket\Server\Connection\AttributeStoreInterface;
use BabDev\WebSocket\Server\Connection\ReactSocketConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Socket\ConnectionInterface as ReactSocketConnectionInterface;

final class ReactSocketConnectionTest extends TestCase
{
    private MockObject&ReactSocketConnectionInterface $reactConnection;

    private MockObject&AttributeStoreInterface $attributeStore;

    private ReactSocketConnection $connection;

    protected function setUp(): void
    {
        $this->reactConnection = $this->createMock(ReactSocketConnectionInterface::class);
        $this->attributeStore = $this->createMock(AttributeStoreInterface::class);

        $this->connection = new ReactSocketConnection($this->reactConnection, $this->attributeStore);
    }

    public function testProvidesTheAttributeStore(): void
    {
        $this->assertSame($this->attributeStore, $this->connection->getAttributeStore());
    }

    public function testProvidesTheReactConnection(): void
    {
        $this->assertSame($this->reactConnection, $this->connection->getConnection());
    }

    public function testSendsAMessage(): void
    {
        $message = 'Hello World!';

        $this->reactConnection->expects($this->once())
            ->method('write')
            ->with($message);

        $this->connection->send($message);
    }

    public function testClosesAConnection(): void
    {
        $this->reactConnection->expects($this->once())
            ->method('end');

        $this->connection->close();
    }
}
