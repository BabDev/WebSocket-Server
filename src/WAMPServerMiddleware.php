<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;

/**
 * The WAMP server middleware interface defines a server middleware component which handles incoming WAMP messages.
 */
interface WAMPServerMiddleware extends WebSocketServerMiddleware
{
    /**
     * Handles an RPC "CALL" WAMP message from the client.
     *
     * @param string $id          The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     * @param string $resolvedUri The URI that identifies the remote procedure, after resolving any CURIE prefixed URIs
     */
    public function onCall(WAMPConnection $connection, string $id, string $resolvedUri, array $params): void;

    /**
     * Handles a "SUBSCRIBE" WAMP message from the client.
     */
    public function onSubscribe(WAMPConnection $connection, Topic $topic): void;

    /**
     * Handles an "UNSUBSCRIBE" WAMP message from the client.
     */
    public function onUnsubscribe(WAMPConnection $connection, Topic $topic): void;

    /**
     * Handles a "PUBLISH" WAMP message from the client.
     *
     * @param array|string $event    The event payload for the message
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function onPublish(WAMPConnection $connection, Topic $topic, array|string $event, array $exclude, array $eligible): void;
}
