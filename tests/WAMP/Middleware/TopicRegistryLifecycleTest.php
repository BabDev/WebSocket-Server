<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\TopicMessageHandler;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\Exception\RouteNotFound;
use BabDev\WebSocket\Server\WAMP\MessageHandler\DefaultMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\MessageType;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * Tests the topic registry lifecycle across the WAMP middleware stack, ensuring client messages cannot register topics
 * which are never cleaned up.
 */
final class TopicRegistryLifecycleTest extends TestCase
{
    private ArrayTopicRegistry $topicRegistry;

    private ParseWAMPMessage $middleware;

    private Connection $connection;

    protected function setUp(): void
    {
        $handler = new class implements TopicMessageHandler {
            public function onSubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void
            {
                if (str_ends_with($topic->id, '/forbidden')) {
                    throw new \RuntimeException('Subscription denied');
                }
            }

            public function onUnsubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void {}

            public function onPublish(Connection $connection, Topic $topic, WAMPMessageRequest $request, mixed $event, array $exclude, array $eligible): void {}
        };

        $routes = new RouteCollection();
        $routes->add('topic', new Route('/topic/{id}', ['_controller' => $handler]));

        $this->topicRegistry = new ArrayTopicRegistry();

        $this->middleware = new ParseWAMPMessage(
            new UpdateTopicSubscriptions(
                new DispatchMessageToHandler(new UrlMatcher($routes, new RequestContext()), new DefaultMessageHandlerResolver()),
                $this->topicRegistry,
            ),
            $this->topicRegistry,
        );

        $attributeStore = new ArrayAttributeStore();

        $connection = $this->createStub(Connection::class);
        $connection->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->connection = $connection;

        $this->middleware->onOpen($this->connection);
    }

    #[TestDox('Does not register topics for "UNSUBSCRIBE" messages to topics the connection is not subscribed to')]
    public function testUnsubscribeFromUnsubscribedTopicsDoesNotRegisterTopics(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->send([MessageType::UNSUBSCRIBE, "/topic/{$i}"]);
        }

        $this->assertRegisteredTopics([]);
    }

    #[TestDox('Does not register topics for "PUBLISH" messages to topics without subscribers')]
    public function testPublishToTopicsWithoutSubscribersDoesNotRegisterTopics(): void
    {
        for ($i = 0; $i < 10; ++$i) {
            $this->send([MessageType::PUBLISH, "/topic/{$i}", 'Hello']);
        }

        $this->assertRegisteredTopics([]);
    }

    #[TestDox('Does not register topics for "PUBLISH" messages to topics without a route')]
    public function testPublishToUnroutableTopicDoesNotRegisterTopic(): void
    {
        try {
            $this->send([MessageType::PUBLISH, '/unknown', 'Hello']);

            self::fail(\sprintf('A %s exception should have been thrown.', RouteNotFound::class));
        } catch (RouteNotFound) {
            // Expected
        }

        $this->assertRegisteredTopics([]);
    }

    #[TestDox('Does not register topics for "SUBSCRIBE" messages to topics without a route')]
    public function testSubscribeToUnroutableTopicDoesNotRegisterTopic(): void
    {
        try {
            $this->send([MessageType::SUBSCRIBE, '/unknown']);

            self::fail(\sprintf('A %s exception should have been thrown.', RouteNotFound::class));
        } catch (RouteNotFound) {
            // Expected
        }

        $this->assertRegisteredTopics([]);
        $this->assertSubscriptionCount(0);
    }

    #[TestDox('Rolls back the subscription when the message handler fails to process a "SUBSCRIBE" message')]
    public function testSubscribeRejectedByHandlerIsRolledBack(): void
    {
        try {
            $this->send([MessageType::SUBSCRIBE, '/topic/forbidden']);

            self::fail('The message handler exception should have been rethrown.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Subscription denied', $exception->getMessage());
        }

        $this->assertRegisteredTopics([]);
        $this->assertSubscriptionCount(0);
    }

    #[TestDox('Registers a topic when subscribed and removes it once the last subscriber leaves')]
    public function testTopicIsRegisteredUntilLastSubscriberLeaves(): void
    {
        $this->send([MessageType::SUBSCRIBE, '/topic/1']);

        $this->assertRegisteredTopics(['/topic/1']);
        $this->assertSubscriptionCount(1);

        $this->send([MessageType::PUBLISH, '/topic/1', 'Hello']);

        $this->assertRegisteredTopics(['/topic/1']);

        $this->send([MessageType::UNSUBSCRIBE, '/topic/1']);

        $this->assertRegisteredTopics([]);
        $this->assertSubscriptionCount(0);
    }

    #[TestDox('Removes subscribed topics from the registry when the connection is closed')]
    public function testTopicIsRemovedWhenConnectionCloses(): void
    {
        $this->send([MessageType::SUBSCRIBE, '/topic/1']);
        $this->send([MessageType::SUBSCRIBE, '/topic/2']);

        $this->assertRegisteredTopics(['/topic/1', '/topic/2']);

        $this->middleware->onClose($this->connection);

        $this->assertRegisteredTopics([]);
    }

    /**
     * @param list<mixed> $message
     */
    private function send(array $message): void
    {
        $this->middleware->onMessage($this->connection, json_encode($message, \JSON_THROW_ON_ERROR));
    }

    /**
     * @param list<string> $expected
     */
    private function assertRegisteredTopics(array $expected): void
    {
        $this->assertSame($expected, array_keys([...$this->topicRegistry->all()]));
    }

    private function assertSubscriptionCount(int $expected): void
    {
        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = $this->connection->getAttributeStore()->get('wamp.subscriptions');

        $this->assertCount($expected, $subscriptions);
    }
}
