<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessage;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;

/**
 * The WAMP connection is a connection class decorating another {@see Connection} adding helper methods to
 * send WAMP messages to the connected client.
 */
interface WAMPConnection extends WebSocketConnection
{
    final public const CURIE_SEPARATOR = ':';

    /**
     * Sends a "CALLRESULT" WAMP message to the client.
     *
     * @param string $id     The unique ID given by the client to respond to
     * @param mixed  $result The call result, must be a JSON serializable value
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function callResult(string $id, mixed $result = null): void;

    /**
     * Sends a "CALLERROR" WAMP message to the client.
     *
     * @param string $id               The unique ID given by the client to respond to
     * @param string $errorUri         The URI given to identify the error
     * @param string $errorDescription An optional human-readable description of the error
     * @param mixed  $errorDetails     Used to communicate application error details defined by the error URI; if given, must be a JSON serializable value
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function callError(string $id, string $errorUri, string $errorDescription = '', mixed $errorDetails = null): void;

    /**
     * Sends a "EVENT" WAMP message to the client.
     *
     * @param string $topicUri The topic to broadcast to
     * @param mixed  $event    Data to send with the event, must be a JSON serializable value
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function event(string $topicUri, mixed $event): void;

    /**
     * Sends a "PREFIX" WAMP message to the client.
     *
     * @param string $prefix The string to use as the prefix
     * @param string $uri    The URI which will be abbreviated with the given prefix
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function prefix(string $prefix, string $uri): void;

    public function getUri(string $uri): string;
}
