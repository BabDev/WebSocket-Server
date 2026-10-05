<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;

/**
 * The topic message handler interface defines a handler for incoming PubSub WAMP messages.
 */
interface TopicMessageHandler extends MessageMiddleware
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
     * @param mixed        $event    The event payload for the message, which may be any decoded JSON value
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function onPublish(WAMPConnection $connection, Topic $topic, WAMPMessageRequest $request, mixed $event, array $exclude, array $eligible): void;
}
