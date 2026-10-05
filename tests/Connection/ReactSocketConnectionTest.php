<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Connection;

use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Connection\ReactSocketConnection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use React\Socket\ConnectionInterface as ReactSocketConnectionInterface;

final class ReactSocketConnectionTest extends TestCase
{
    private readonly MockObject&ReactSocketConnectionInterface $reactConnection;

    private readonly Stub&AttributeStore $attributeStore;

    private readonly ReactSocketConnection $connection;

    protected function setUp(): void
    {
        $this->reactConnection = $this->createMock(ReactSocketConnectionInterface::class);
        $this->attributeStore = $this->createStub(AttributeStore::class);

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

    #[TestDox('Closes the connection when too much data is written after the write buffer is full')]
    public function testClosesTheConnectionWhenTheWriteBufferLimitIsExceeded(): void
    {
        $writes = [];

        $this->reactConnection->expects($this->once())
            ->method('on')
            ->with('drain', $this->isCallable());

        $this->reactConnection->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(static function (string $data) use (&$writes): bool {
                $writes[] = $data;

                // The write buffer is full after the first write
                return false;
            });

        $this->reactConnection->expects($this->once())
            ->method('close');

        $connection = new ReactSocketConnection($this->reactConnection, $this->attributeStore, 10);
        $connection->send('first');
        $connection->send('second');
        $connection->send('third');

        $this->assertSame(['first', 'second'], $writes, 'Data over the limit should not be written.');
    }

    #[TestDox('Resets the write buffer limit once the write buffer is drained')]
    public function testResetsTheWriteBufferLimitWhenDrained(): void
    {
        $onDrain = null;

        $this->reactConnection->expects($this->once())
            ->method('on')
            ->with('drain', $this->isCallable())
            ->willReturnCallback(static function (string $event, callable $listener) use (&$onDrain): void {
                $onDrain = $listener;
            });

        $this->reactConnection->expects($this->exactly(4))
            ->method('write')
            ->willReturn(false);

        $this->reactConnection->expects($this->never())
            ->method('close');

        $connection = new ReactSocketConnection($this->reactConnection, $this->attributeStore, 10);
        $connection->send('first');
        $connection->send('second');

        $this->assertIsCallable($onDrain);

        $onDrain();

        // Without the reset, these writes would exceed the limit
        $connection->send('third');
        $connection->send('fourth');
    }

    #[TestDox('Does not limit the data written when the write buffer limit is disabled')]
    public function testDoesNotLimitTheWriteBufferWithoutALimit(): void
    {
        $this->reactConnection->expects($this->never())
            ->method('on');

        $this->reactConnection->expects($this->exactly(3))
            ->method('write')
            ->willReturn(false);

        $this->reactConnection->expects($this->never())
            ->method('close');

        $this->connection->send(str_repeat('a', 1024));
        $this->connection->send(str_repeat('b', 1024));
        $this->connection->send(str_repeat('c', 1024));
    }
}
