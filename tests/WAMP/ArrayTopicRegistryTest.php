<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP;

use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\Exception\TopicAlreadyRegistered;
use BabDev\WebSocket\Server\WAMP\Exception\TopicNotFound;
use BabDev\WebSocket\Server\WAMP\Topic;
use PHPUnit\Framework\TestCase;

final class ArrayTopicRegistryTest extends TestCase
{
    private ArrayTopicRegistry $topicRegistry;

    protected function setUp(): void
    {
        $this->topicRegistry = new ArrayTopicRegistry();
    }

    public function testCanAddATopic(): void
    {
        $topic = new Topic('testing/123');

        $this->topicRegistry->add($topic);

        $this->assertTrue($this->topicRegistry->has($topic->id));
    }

    public function testCanNotAddATopicWhenAnotherHasTheSameId(): void
    {
        $this->expectException(TopicAlreadyRegistered::class);

        $topic = new Topic('testing/123');

        $this->topicRegistry->add($topic);
        $this->topicRegistry->add($topic);
    }

    public function testCanRetrieveATopic(): void
    {
        $topic = new Topic('testing/123');

        $this->topicRegistry->add($topic);

        $this->assertSame($topic, $this->topicRegistry->get($topic->id));
    }

    public function testCanNotRetrieveAnUnregisteredTopic(): void
    {
        $this->expectException(TopicNotFound::class);

        $this->topicRegistry->get('testing/123');
    }

    public function testReportsIfATopicExists(): void
    {
        $topic = new Topic('testing/123');

        $this->topicRegistry->add($topic);

        $this->assertTrue($this->topicRegistry->has($topic->id));

        $this->topicRegistry->remove($topic);

        $this->assertFalse($this->topicRegistry->has($topic->id));
    }
}
