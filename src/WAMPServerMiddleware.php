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
     * @param string $id The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     */
    public function onCall(WAMPConnection $connection, string $id, Topic $topic, array $params): void;

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
     * @param array        $exclude  A list of session IDs the message should be excluded from
     * @param array        $eligible A list of session IDs the message should be sent to
     *
     * @phpstan-param list<string> $exclude
     * @phpstan-param list<string> $eligible
     */
    public function onPublish(WAMPConnection $connection, Topic $topic, array|string $event, array $exclude, array $eligible): void;
}
