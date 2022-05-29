<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Exception\UnsupportedConnection;
use BabDev\WebSocket\Server\WAMP\MessageType;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TopicTest extends TestCase
{
    private Topic $topic;

    protected function setUp(): void
    {
        $this->topic = new Topic('testing/123');
    }

    public function testCanBeCastToString(): void
    {
        $this->assertSame('testing/123', (string) $this->topic);
    }

    public function testConnectionsCanBeAddedAndRemovedFromATopic(): void
    {
        $connection1 = new WAMPConnection($this->createMock(Connection::class));
        $connection2 = new WAMPConnection($this->createMock(Connection::class));
        $connection3 = new WAMPConnection($this->createMock(Connection::class));

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $this->assertCount(3, $this->topic);

        $this->topic->has($connection1);
        $this->topic->has($connection2);
        $this->topic->has($connection3);

        $this->topic->remove($connection1);
        $this->topic->remove($connection2);
        $this->topic->remove($connection3);

        $this->assertCount(0, $this->topic);
    }

    public function testRejectsConnectionsWhichAreNotAWampConnection(): void
    {
        $this->expectException(UnsupportedConnection::class);

        $this->topic->add($this->createMock(Connection::class));
    }

    public function testCanBeIterated(): void
    {
        $connection1 = new WAMPConnection($this->createMock(Connection::class));
        $connection2 = new WAMPConnection($this->createMock(Connection::class));
        $connection3 = new WAMPConnection($this->createMock(Connection::class));

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $items = 0;

        foreach ($this->topic as $connection) {
            $items++;
        }

        $this->assertSame(3, $items);
    }

    public function testBroadcastsToAllSubscribersByDefault(): void
    {
        $data = ['hello' => 'world'];

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->exactly(3))
            ->method('get')
            ->with('wamp.session_id')
            ->willReturnOnConsecutiveCalls(bin2hex(random_bytes(32)), bin2hex(random_bytes(32)), bin2hex(random_bytes(32)));

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->exactly(3))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->exactly(3))
            ->method('send')
            ->with(json_encode([MessageType::EVENT, (string) $this->topic, $data]));

        $connection1 = new WAMPConnection($decoratedConnection);
        $connection2 = new WAMPConnection($decoratedConnection);
        $connection3 = new WAMPConnection($decoratedConnection);

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $this->topic->broadcast($data);
    }

    public function testBroadcastsOnlyToEligibleSubscribersWhenConfigured(): void
    {
        $data = ['hello' => 'world'];

        $connection1SessionId = bin2hex(random_bytes(32));
        $connection2SessionId = bin2hex(random_bytes(32));
        $connection3SessionId = bin2hex(random_bytes(32));

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->exactly(3))
            ->method('get')
            ->with('wamp.session_id')
            ->willReturnOnConsecutiveCalls($connection1SessionId, $connection2SessionId, $connection3SessionId);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->exactly(3))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->exactly(2))
            ->method('send')
            ->with(json_encode([MessageType::EVENT, (string) $this->topic, $data]));

        $connection1 = new WAMPConnection($decoratedConnection);
        $connection2 = new WAMPConnection($decoratedConnection);
        $connection3 = new WAMPConnection($decoratedConnection);

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $this->topic->broadcast($data, [], [$connection1SessionId, $connection3SessionId]);
    }

    public function testDoesNotBroadcastToExcludedSubscribersWhenConfigured(): void
    {
        $data = ['hello' => 'world'];

        $connection1SessionId = bin2hex(random_bytes(32));
        $connection2SessionId = bin2hex(random_bytes(32));
        $connection3SessionId = bin2hex(random_bytes(32));

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->exactly(3))
            ->method('get')
            ->with('wamp.session_id')
            ->willReturnOnConsecutiveCalls($connection1SessionId, $connection2SessionId, $connection3SessionId);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->exactly(3))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->exactly(2))
            ->method('send')
            ->with(json_encode([MessageType::EVENT, (string) $this->topic, $data]));

        $connection1 = new WAMPConnection($decoratedConnection);
        $connection2 = new WAMPConnection($decoratedConnection);
        $connection3 = new WAMPConnection($decoratedConnection);

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $this->topic->broadcast($data, [$connection2SessionId], []);
    }

    public function testOnlyBroadcastsToEligibleSubscribersWhenBothEligibleAndExcludeAreConfigured(): void
    {
        $data = ['hello' => 'world'];

        $connection1SessionId = bin2hex(random_bytes(32));
        $connection2SessionId = bin2hex(random_bytes(32));
        $connection3SessionId = bin2hex(random_bytes(32));

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->exactly(3))
            ->method('get')
            ->with('wamp.session_id')
            ->willReturnOnConsecutiveCalls($connection1SessionId, $connection2SessionId, $connection3SessionId);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->exactly(3))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedConnection->expects($this->once())
            ->method('send')
            ->with(json_encode([MessageType::EVENT, (string) $this->topic, $data]));

        $connection1 = new WAMPConnection($decoratedConnection);
        $connection2 = new WAMPConnection($decoratedConnection);
        $connection3 = new WAMPConnection($decoratedConnection);

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $this->topic->broadcast($data, [$connection2SessionId], [$connection1SessionId]);
    }
}
