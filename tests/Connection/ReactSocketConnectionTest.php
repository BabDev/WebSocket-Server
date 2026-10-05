<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Connection;

use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Connection\ReactSocketConnection;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\Socket\ConnectionInterface as ReactSocketConnectionInterface;

final class ReactSocketConnectionTest extends TestCase
{
    public function testProvidesTheAttributeStore(): void
    {
        $attributeStore = $this->createStub(AttributeStore::class);

        $this->assertSame($attributeStore, $this->createConnection(attributeStore: $attributeStore)->getAttributeStore());
    }

    public function testProvidesTheReactConnection(): void
    {
        $reactConnection = $this->createStub(ReactSocketConnectionInterface::class);

        $this->assertSame($reactConnection, $this->createConnection($reactConnection)->getConnection());
    }

    public function testSendsAMessage(): void
    {
        /** @var MockObject&ReactSocketConnectionInterface $reactConnection */
        $reactConnection = $this->createMock(ReactSocketConnectionInterface::class);

        $message = 'Hello World!';

        $reactConnection->expects($this->once())
            ->method('write')
            ->with($message);

        $this->createConnection($reactConnection)->send($message);
    }

    public function testClosesAConnection(): void
    {
        /** @var MockObject&ReactSocketConnectionInterface $reactConnection */
        $reactConnection = $this->createMock(ReactSocketConnectionInterface::class);

        $reactConnection->expects($this->once())
            ->method('end');

        $this->createConnection($reactConnection)->close();
    }

    #[TestDox('Closes the connection when too much data is written after the write buffer is full')]
    public function testClosesTheConnectionWhenTheWriteBufferLimitIsExceeded(): void
    {
        /** @var MockObject&ReactSocketConnectionInterface $reactConnection */
        $reactConnection = $this->createMock(ReactSocketConnectionInterface::class);

        $writes = [];

        $reactConnection->expects($this->once())
            ->method('on')
            ->with('drain', $this->isCallable());

        $reactConnection->expects($this->exactly(2))
            ->method('write')
            ->willReturnCallback(static function (string $data) use (&$writes): bool {
                $writes[] = $data;

                // The write buffer is full after the first write
                return false;
            });

        $reactConnection->expects($this->once())
            ->method('close');

        $connection = $this->createConnection($reactConnection, writeBufferLimit: 10);
        $connection->send('first');
        $connection->send('second');
        $connection->send('third');

        $this->assertSame(['first', 'second'], $writes, 'Data over the limit should not be written.');
    }

    #[TestDox('Resets the write buffer limit once the write buffer is drained')]
    public function testResetsTheWriteBufferLimitWhenDrained(): void
    {
        /** @var MockObject&ReactSocketConnectionInterface $reactConnection */
        $reactConnection = $this->createMock(ReactSocketConnectionInterface::class);

        $onDrain = null;

        $reactConnection->expects($this->once())
            ->method('on')
            ->with('drain', $this->isCallable())
            ->willReturnCallback(static function (string $event, callable $listener) use (&$onDrain): void {
                $onDrain = $listener;
            });

        $reactConnection->expects($this->exactly(4))
            ->method('write')
            ->willReturn(false);

        $reactConnection->expects($this->never())
            ->method('close');

        $connection = $this->createConnection($reactConnection, writeBufferLimit: 10);
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
        /** @var MockObject&ReactSocketConnectionInterface $reactConnection */
        $reactConnection = $this->createMock(ReactSocketConnectionInterface::class);

        $reactConnection->expects($this->never())
            ->method('on');

        $reactConnection->expects($this->exactly(3))
            ->method('write')
            ->willReturn(false);

        $reactConnection->expects($this->never())
            ->method('close');

        $connection = $this->createConnection($reactConnection);
        $connection->send(str_repeat('a', 1024));
        $connection->send(str_repeat('b', 1024));
        $connection->send(str_repeat('c', 1024));
    }

    /**
     * @param int<1, max>|null $writeBufferLimit
     */
    private function createConnection(?ReactSocketConnectionInterface $reactConnection = null, ?AttributeStore $attributeStore = null, ?int $writeBufferLimit = null): ReactSocketConnection
    {
        return new ReactSocketConnection(
            $reactConnection ?? $this->createStub(ReactSocketConnectionInterface::class),
            $attributeStore ?? $this->createStub(AttributeStore::class),
            $writeBufferLimit,
        );
    }
}
