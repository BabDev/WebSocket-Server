<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;

/**
 * The topic message middleware interface defines a message middleware component for incoming PubSub WAMP messages.
 */
interface TopicMessageMiddleware extends MessageMiddleware
{
    /**
     * Handles a "SUBSCRIBE" WAMP message from the client.
     */
    public function onSubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void;

    /**
     * Handles an "UNSUBSCRIBE" WAMP message from the client.
     */
    public function onUnsubscribe(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request): void;

    /**
     * Handles a "PUBLISH" WAMP message from the client.
     *
     * @param array|string $event    The event payload for the message
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function onPublish(Connection $connection, Topic $topic, WAMPMessageRequest $request, array|string $event, array $exclude, array $eligible): void;
}
