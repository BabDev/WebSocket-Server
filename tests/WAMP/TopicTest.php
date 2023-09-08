<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Exception\UnsupportedConnection;
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
        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);

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
        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $items = 0;

        foreach ($this->topic as $connection) {
            ++$items;
        }

        $this->assertSame(3, $items);
    }

    public function testBroadcastsToAllSubscribersByDefault(): void
    {
        $data = ['hello' => 'world'];

        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);
        $connection1->expects($this->never())
            ->method('getAttributeStore');

        $connection1->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);
        $connection2->expects($this->never())
            ->method('getAttributeStore');

        $connection2->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);
        $connection3->expects($this->never())
            ->method('getAttributeStore');

        $connection3->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

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

        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);
        $connection1->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection1->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);
        $connection2->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection2->expects($this->never())
            ->method('event');

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);
        $connection3->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection3->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

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

        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);
        $connection1->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection1->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);
        $connection2->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection2->expects($this->never())
            ->method('event');

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);
        $connection3->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection3->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

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

        /** @var MockObject&WAMPConnection $connection1 */
        $connection1 = $this->createMock(WAMPConnection::class);
        $connection1->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection1->expects($this->once())
            ->method('event')
            ->with($this->topic->id, $data);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);
        $connection2->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection2->expects($this->never())
            ->method('event');

        /** @var MockObject&WAMPConnection $connection3 */
        $connection3 = $this->createMock(WAMPConnection::class);
        $connection3->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection3->expects($this->never())
            ->method('event');

        $this->topic->add($connection1);
        $this->topic->add($connection2);
        $this->topic->add($connection3);

        $this->topic->broadcast($data, [$connection2SessionId], [$connection1SessionId]);
    }
}
