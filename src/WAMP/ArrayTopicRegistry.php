<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use BabDev\WebSocket\Server\WAMP\Exception\TopicAlreadyRegistered;
use BabDev\WebSocket\Server\WAMP\Exception\TopicNotFound;

/**
 * The array topic registry uses a PHP array as the storage layer for the registry.
 */
final class ArrayTopicRegistry implements TopicRegistry
{
    /**
     * @var array<string, Topic>
     */
    private array $topics = [];

    /**
     * @throws TopicAlreadyRegistered if another topic with the same ID is already registered
     */
    public function add(Topic $topic): void
    {
        if ($this->has($topic->id)) {
            throw new TopicAlreadyRegistered(sprintf('A topic for URI "%s" is already registered.', $topic->id));
        }

        $this->topics[$topic->id] = $topic;
    }

    /**
     * @return iterable<Topic>
     */
    public function all(): iterable
    {
        return $this->topics;
    }

    /**
     * @throws TopicNotFound if a topic with the requested ID does not exist
     */
    public function get(string $id): Topic
    {
        return $this->topics[$id] ?? throw new TopicNotFound(sprintf('A topic for URI "%s" is not registered.', $id));
    }

    public function has(string $id): bool
    {
        return isset($this->topics[$id]);
    }

    public function remove(Topic $topic): void
    {
        unset($this->topics[$topic->id]);
    }
}
