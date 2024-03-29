<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMPServerMiddleware;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateTopicSubscriptionsTest extends TestCase
{
    private MockObject&WAMPServerMiddleware $decoratedMiddleware;

    private ArrayTopicRegistry $topicRegistry;

    private UpdateTopicSubscriptions $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(WAMPServerMiddleware::class);
        $this->topicRegistry = new ArrayTopicRegistry();

        $this->middleware = new UpdateTopicSubscriptions($this->decoratedMiddleware, $this->topicRegistry);
    }

    public function testGetSubProtocols(): void
    {
        $this->decoratedMiddleware->expects($this->once())
            ->method('getSubProtocols')
            ->willReturn(['wamp']);

        $this->assertSame(
            ['wamp'],
            $this->middleware->getSubProtocols(),
        );
    }

    #[TestDox('Handles a new connection being opened')]
    public function testOnOpen(): void
    {
        $attributeStore = new ArrayAttributeStore();

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->onOpen($connection);

        $this->assertInstanceOf(\SplObjectStorage::class, $attributeStore->get('wamp.subscriptions'));
    }

    #[TestDox('Handles incoming data on the connection')]
    public function testOnMessage(): void
    {
        $data = 'Testing';

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $data);

        $this->middleware->onMessage($connection, $data);
    }

    #[TestDox('Closes the connection')]
    public function testOnClose(): void
    {
        $topic1 = new Topic('testing/1');
        $topic2 = new Topic('testing/2');
        $topic3 = new Topic('testing/3');

        $attributeStore = new ArrayAttributeStore();

        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = new \SplObjectStorage();
        $subscriptions->attach($topic1);
        $subscriptions->attach($topic2);
        $subscriptions->attach($topic3);

        $attributeStore->set('wamp.subscriptions', $subscriptions);

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        /** @var MockObject&WAMPConnection $connection2 */
        $connection2 = $this->createMock(WAMPConnection::class);
        $connection2->expects($this->never())
            ->method('getAttributeStore');

        $topic1->add($connection);
        $topic2->add($connection);
        $topic3->add($connection);
        $topic3->add($connection2);

        $this->topicRegistry->add($topic1);
        $this->topicRegistry->add($topic2);
        $this->topicRegistry->add($topic3);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->middleware->onClose($connection);

        $this->assertFalse($this->topicRegistry->has($topic1->id));
        $this->assertFalse($this->topicRegistry->has($topic2->id));
        $this->assertTrue($this->topicRegistry->has($topic3->id));
    }

    #[TestDox('Handles an error')]
    public function testOnError(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $error = new \Exception('Testing');

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $error);

        $this->middleware->onError($connection, $error);
    }

    #[TestDox('Handles an RPC "CALL" WAMP message')]
    public function testOnCall(): void
    {
        $id = uniqid();
        $resolvedUri = '/testing';
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onCall')
            ->with($connection, $id, $resolvedUri, $params);

        $this->middleware->onCall($connection, $id, $resolvedUri, $params);
    }

    #[TestDox('Handles a "SUBSCRIBE" WAMP message')]
    public function testOnSubscribe(): void
    {
        $topic = new Topic('testing');

        $this->topicRegistry->add($topic);

        $attributeStore = new ArrayAttributeStore();

        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = new \SplObjectStorage();

        $attributeStore->set('wamp.subscriptions', $subscriptions);

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onSubscribe')
            ->with($connection, $topic);

        $this->middleware->onSubscribe($connection, $topic);

        $this->assertTrue($subscriptions->contains($topic));
        $this->assertTrue($topic->has($connection));
    }

    #[TestDox('Handles an "UNSUBSCRIBE" WAMP message')]
    public function testOnUnsubscribe(): void
    {
        $topic = new Topic('testing');

        $this->topicRegistry->add($topic);

        $attributeStore = new ArrayAttributeStore();

        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = new \SplObjectStorage();
        $subscriptions->attach($topic);

        $attributeStore->set('wamp.subscriptions', $subscriptions);

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $topic->add($connection);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onUnsubscribe')
            ->with($connection, $topic);

        $this->middleware->onUnsubscribe($connection, $topic);

        $this->assertFalse($subscriptions->contains($topic));
        $this->assertFalse($this->topicRegistry->has($topic->id));
    }

    #[TestDox('Handles a "PUBLISH" WAMP message')]
    public function testOnPublish(): void
    {
        $topic = new Topic('testing');
        $event = ['foo' => 'bar'];
        $exclude = [];
        $eligible = [];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onPublish')
            ->with($connection, $topic, $event, $exclude, $eligible);

        $this->middleware->onPublish($connection, $topic, $event, $exclude, $eligible);
    }
}
