<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMPServerMiddleware;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class UpdateTopicSubscriptionsTest extends TestCase
{
    private MockObject & WAMPServerMiddleware $decoratedMiddleware;

    private MockObject & TopicRegistry $topicRegistry;

    private UpdateTopicSubscriptions $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(WAMPServerMiddleware::class);
        $this->topicRegistry = $this->createMock(TopicRegistry::class);

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

    /**
     * @testdox Handles a new connection being opened
     */
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

    /**
     * @testdox Handles incoming data on the connection
     */
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

    /**
     * @testdox Closes the connection
     */
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

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection = new WAMPConnection($decoratedConnection);
        $connection2 = new WAMPConnection($decoratedConnection);

        $topic1->add($connection);
        $topic2->add($connection);
        $topic3->add($connection);
        $topic3->add($connection2);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->topicRegistry->expects($this->once())
            ->method('all')
            ->willReturn([$topic1, $topic2, $topic3]);

        $this->topicRegistry->expects($this->exactly(2))
            ->method('remove')
            ->withConsecutive(
                [$topic1],
                [$topic2],
            );

        $this->middleware->onClose($connection);
    }

    /**
     * @testdox Handles an error
     */
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

    /**
     * @testdox Handles an RPC "CALL" WAMP message
     */
    public function testOnCall(): void
    {
        $id = uniqid();
        $topic = new Topic('testing');
        $params = ['foo' => 'bar'];

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onCall')
            ->with($connection, $id, $topic, $params);

        $this->middleware->onCall($connection, $id, $topic, $params);
    }

    /**
     * @testdox Handles a "SUBSCRIBE" WAMP message
     */
    public function testOnSubscribe(): void
    {
        $topic = new Topic('testing');

        $attributeStore = new ArrayAttributeStore();

        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = new \SplObjectStorage();

        $attributeStore->set('wamp.subscriptions', $subscriptions);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection = new WAMPConnection($decoratedConnection);

        $this->topicRegistry->expects($this->once())
            ->method('has')
            ->with($topic->id)
            ->willReturn(true);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onSubscribe')
            ->with($connection, $topic);

        $this->middleware->onSubscribe($connection, $topic);

        $this->assertTrue($subscriptions->contains($topic));
        $this->assertTrue($topic->has($connection));
    }

    /**
     * @testdox Handles an "UNSUBSCRIBE" WAMP message
     */
    public function testOnUnsubscribe(): void
    {
        $topic = new Topic('testing');

        $attributeStore = new ArrayAttributeStore();

        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = new \SplObjectStorage();
        $subscriptions->attach($topic);

        $attributeStore->set('wamp.subscriptions', $subscriptions);

        /** @var MockObject&Connection $decoratedConnection */
        $decoratedConnection = $this->createMock(Connection::class);
        $decoratedConnection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection = new WAMPConnection($decoratedConnection);

        $topic->add($connection);

        $this->topicRegistry->expects($this->once())
            ->method('remove')
            ->with($topic);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onUnsubscribe')
            ->with($connection, $topic);

        $this->middleware->onUnsubscribe($connection, $topic);

        $this->assertFalse($subscriptions->contains($topic));
    }

    /**
     * @testdox Handles a "PUBLISH" WAMP message
     */
    public function testOnPublish(): void
    {
        $topic = new Topic('testing');
        $event = ['foo' => 'bar'];
        $exclude = [];
        $eligible = [];

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onPublish')
            ->with($connection, $topic, $event, $exclude, $eligible);

        $this->middleware->onPublish($connection, $topic, $event, $exclude, $eligible);
    }
}
