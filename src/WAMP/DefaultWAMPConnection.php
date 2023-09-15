<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Exception\UnsupportedConnection;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessage;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;
use Ratchet\RFC6455\Messaging\DataInterface;

/**
 * The WAMP connection is a connection class decorating another {@see Connection} adding helper methods to
 * send WAMP messages to the connected client.
 */
final readonly class DefaultWAMPConnection implements WAMPConnection
{
    public function __construct(private Connection $connection) {}

    public function getAttributeStore(): AttributeStore
    {
        return $this->connection->getAttributeStore();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    /**
     * @throws UnsupportedConnection if a {@see DataInterface} is provided and the connection does not decorate a {@see WebSocketConnection}
     */
    public function send(string|DataInterface $data): void
    {
        if ($data instanceof DataInterface && !$this->connection instanceof WebSocketConnection) {
            throw new UnsupportedConnection(sprintf('To send a "%s" implementation, "%s" must decorate an instance of "%s".', DataInterface::class, self::class, WebSocketConnection::class));
        }

        if ($this->connection instanceof WebSocketConnection) {
            $this->connection->send($data);
        } elseif ($data instanceof DataInterface) {
            $this->connection->send($data->getContents());
        } else {
            $this->connection->send($data);
        }
    }

    public function close(mixed $data = null): void
    {
        $this->connection->close($data);
    }

    /**
     * Sends a "CALLRESULT" WAMP message to the client.
     *
     * @param string $id     The unique ID given by the client to respond to
     * @param mixed  $result The call result, must be a JSON serializable value
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function callResult(string $id, mixed $result = null): void
    {
        try {
            $this->send(json_encode([MessageType::CALL_RESULT, $id, $result], \JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new InvalidMessage($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

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
    public function callError(string $id, string $errorUri, string $errorDescription = '', mixed $errorDetails = null): void
    {
        $data = [MessageType::CALL_ERROR, $id, $errorUri, $errorDescription];

        if (null !== $errorDetails) {
            $data[] = $errorDetails;
        }

        try {
            $this->send(json_encode($data, \JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new InvalidMessage($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Sends a "EVENT" WAMP message to the client.
     *
     * @param string $topicUri The topic to broadcast to
     * @param mixed  $event    Data to send with the event, must be a JSON serializable value
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function event(string $topicUri, mixed $event): void
    {
        try {
            $this->send(json_encode([MessageType::EVENT, $topicUri, $event], \JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new InvalidMessage($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    /**
     * Sends a "PREFIX" WAMP message to the client.
     *
     * @param string $prefix The string to use as the prefix
     * @param string $uri    The URI which will be abbreviated with the given prefix
     *
     * @throws InvalidMessage if the message cannot be JSON encoded
     */
    public function prefix(string $prefix, string $uri): void
    {
        /** @var array<string, string> $prefixes */
        $prefixes = $this->getAttributeStore()->get('wamp.prefixes', []);
        $prefixes[$prefix] = $uri;

        $this->getAttributeStore()->set('wamp.prefixes', $prefixes);

        try {
            $this->send(json_encode([MessageType::PREFIX, $prefix, $uri], \JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new InvalidMessage($exception->getMessage(), $exception->getCode(), $exception);
        }
    }

    public function getUri(string $uri): string
    {
        $hasHttpProtocol = preg_match('/http(s*)\:\/\//', $uri);

        if (false !== $hasHttpProtocol && 0 !== $hasHttpProtocol) {
            return $uri;
        }

        if (!str_contains($uri, self::CURIE_SEPARATOR)) {
            return $uri;
        }

        [$prefix, $action] = explode(self::CURIE_SEPARATOR, $uri);

        /** @var array<string, string> $prefixes */
        $prefixes = $this->getAttributeStore()->get('wamp.prefixes', []);

        if (isset($prefixes[$prefix])) {
            return $prefixes[$prefix].'#'.$action;
        }

        return $uri;
    }
}
