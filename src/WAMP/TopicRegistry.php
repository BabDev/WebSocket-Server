<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use BabDev\WebSocket\Server\WAMP\Exception\TopicAlreadyRegistered;
use BabDev\WebSocket\Server\WAMP\Exception\TopicNotFound;

/**
 * A topic registry is a runtime store containing the active {@see Topic} instances for the server.
 */
interface TopicRegistry
{
    /**
     * @throws TopicAlreadyRegistered if another topic with the same ID is already registered
     */
    public function add(Topic $topic): void;

    /**
     * @throws TopicNotFound if a topic with the requested ID does not exist
     */
    public function get(string $id): Topic;

    public function has(string $id): bool;

    public function remove(Topic $topic): void;
}
