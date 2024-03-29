<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\WAMP\Exception\RouteNotFound;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMPServerMiddleware;

/**
 * The update topic subscriptions server middleware is responsible for updating the list of connections for each
 * active topic.
 */
final readonly class UpdateTopicSubscriptions implements WAMPServerMiddleware
{
    public function __construct(
        private WAMPServerMiddleware $middleware,
        private TopicRegistry $topicRegistry,
    ) {}

    /**
     * @return list<string>
     */
    public function getSubProtocols(): array
    {
        return $this->middleware->getSubProtocols();
    }

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void
    {
        $connection->getAttributeStore()->set('wamp.subscriptions', new \SplObjectStorage());

        $this->middleware->onOpen($connection);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        $this->middleware->onMessage($connection, $data);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        $this->middleware->onClose($connection);

        foreach ($this->topicRegistry->all() as $topic) {
            $this->cleanTopic($topic, $connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->middleware->onError($connection, $throwable);
    }

    /**
     * Handles an RPC "CALL" WAMP message from the client.
     *
     * @param string $id          The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     * @param string $resolvedUri The URI that identifies the remote procedure, after resolving any CURIE prefixed URIs
     */
    public function onCall(WAMPConnection $connection, string $id, string $resolvedUri, array $params): void
    {
        $this->middleware->onCall($connection, $id, $resolvedUri, $params);
    }

    /**
     * Handles a "SUBSCRIBE" WAMP message from the client.
     */
    public function onSubscribe(WAMPConnection $connection, Topic $topic): void
    {
        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = $connection->getAttributeStore()->get('wamp.subscriptions', new \SplObjectStorage());

        if ($subscriptions->contains($topic)) {
            return;
        }

        if (!$this->topicRegistry->has($topic->id)) {
            $this->topicRegistry->add($topic);
        }

        $topic->add($connection);

        $subscriptions->attach($topic);

        try {
            $this->middleware->onSubscribe($connection, $topic);
        } catch (RouteNotFound $exception) {
            $this->cleanTopic($topic, $connection);

            throw $exception;
        }
    }

    /**
     * Handles an "UNSUBSCRIBE" WAMP message from the client.
     */
    public function onUnsubscribe(WAMPConnection $connection, Topic $topic): void
    {
        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = $connection->getAttributeStore()->get('wamp.subscriptions', new \SplObjectStorage());

        if (!$subscriptions->contains($topic)) {
            return;
        }

        $this->cleanTopic($topic, $connection);

        $this->middleware->onUnsubscribe($connection, $topic);
    }

    /**
     * Handles a "PUBLISH" WAMP message from the client.
     *
     * @param array|string $event    The event payload for the message
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function onPublish(WAMPConnection $connection, Topic $topic, array|string $event, array $exclude, array $eligible): void
    {
        try {
            $this->middleware->onPublish($connection, $topic, $event, $exclude, $eligible);
        } catch (RouteNotFound $exception) {
            $this->cleanTopic($topic, $connection);

            throw $exception;
        }
    }

    private function cleanTopic(Topic $topic, Connection $connection): void
    {
        /** @var \SplObjectStorage<Topic, null> $subscriptions */
        $subscriptions = $connection->getAttributeStore()->get('wamp.subscriptions', new \SplObjectStorage());

        if ($subscriptions->contains($topic)) {
            $subscriptions->detach($topic);
        }

        $topic->remove($connection);

        if (0 === $topic->count()) {
            $this->topicRegistry->remove($topic);
        }
    }
}
