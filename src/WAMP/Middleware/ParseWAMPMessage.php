<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Server;
use BabDev\WebSocket\Server\WAMP\DefaultWAMPConnection;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessage;
use BabDev\WebSocket\Server\WAMP\Exception\PrefixLimitExceeded;
use BabDev\WebSocket\Server\WAMP\Exception\UnsupportedMessageType;
use BabDev\WebSocket\Server\WAMP\MessageType;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\TopicRegistry;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMPServerMiddleware;
use BabDev\WebSocket\Server\WebSocketServerMiddleware;

/**
 * The parse WAMP message server middleware parses an incoming WAMP message per the v1 specification for consumption.
 *
 * @see https://web.archive.org/web/20150317205544/http://wamp.ws/spec/wamp1/
 */
final class ParseWAMPMessage implements WebSocketServerMiddleware
{
    private const int WAMP_PROTOCOL_VERSION = 1;

    /**
     * @var \SplObjectStorage<Connection, WAMPConnection>
     */
    private readonly \SplObjectStorage $connections;

    private string $serverIdentity = Server::VERSION;

    /**
     * @var positive-int
     */
    private int $maxPrefixes = 100;

    public function __construct(
        private readonly WAMPServerMiddleware $middleware,
        private readonly TopicRegistry $topicRegistry,
    ) {
        $this->connections = new \SplObjectStorage();
    }

    /**
     * @return list<string>
     */
    public function getSubProtocols(): array
    {
        return [...$this->middleware->getSubProtocols(), ...['wamp']];
    }

    /**
     * Handles a new connection to the server.
     *
     * @throws InvalidMessage if the welcome message cannot be JSON encoded
     */
    public function onOpen(Connection $connection): void
    {
        $decoratedConnection = new DefaultWAMPConnection($connection);
        $decoratedConnection->getAttributeStore()->set(AttributeKey::WAMP_SESSION_ID, $sessionId = bin2hex(random_bytes(32)));
        $decoratedConnection->getAttributeStore()->set(AttributeKey::WAMP_PREFIXES, []);

        try {
            $decoratedConnection->send(json_encode([MessageType::WELCOME, $sessionId, self::WAMP_PROTOCOL_VERSION, $this->serverIdentity], \JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new InvalidMessage($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->connections->offsetSet($connection, $decoratedConnection);

        $this->middleware->onOpen($decoratedConnection);
    }

    /**
     * Handles incoming data on the connection.
     *
     * @throws InvalidMessage         if the WAMP message is badly formatted or contains invalid data
     * @throws PrefixLimitExceeded    if the client has already registered the maximum number of prefixes
     * @throws UnsupportedMessageType if the WAMP message type is not supported
     */
    public function onMessage(Connection $connection, string $data): void
    {
        /** @var WAMPConnection $decoratedConnection */
        $decoratedConnection = $this->connections[$connection];

        try {
            $message = json_decode($data, true, 512, \JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new InvalidMessage('Invalid WAMP message.', $exception->getCode(), $exception);
        }

        if (!\is_array($message) || !array_is_list($message)) {
            throw new InvalidMessage('Invalid WAMP message format.');
        }

        if (!isset($message[0]) || !\is_int($message[0])) {
            throw new InvalidMessage('Invalid WAMP message type, must be an integer.');
        }

        switch ($message[0]) {
            case MessageType::PREFIX:
                $prefix = $this->getStringElement($message, 1, 'prefix');
                $prefixUri = $this->getStringElement($message, 2, 'prefix URI');

                /** @var array<string, string> $prefixes */
                $prefixes = $decoratedConnection->getAttributeStore()->get(AttributeKey::WAMP_PREFIXES, []);

                // Replacing an already registered prefix is allowed when the limit has been reached
                if (!isset($prefixes[$prefix]) && \count($prefixes) >= $this->maxPrefixes) {
                    throw new PrefixLimitExceeded($this->maxPrefixes, \sprintf('Cannot register prefix "%s", the connection has reached the limit of %d prefixes.', $prefix, $this->maxPrefixes));
                }

                $prefixes[$prefix] = $prefixUri;

                $decoratedConnection->getAttributeStore()->set(AttributeKey::WAMP_PREFIXES, $prefixes);

                break;

            case MessageType::CALL:
                $callId = $this->getStringElement($message, 1, 'call ID', allowNumeric: true);
                $procUri = $this->getStringElement($message, 2, 'procedure URI');

                $params = \array_slice($message, 3);

                if (1 === \count($params) && \is_array($params[0])) {
                    $params = $params[0];
                }

                $this->middleware->onCall($decoratedConnection, $callId, $decoratedConnection->getUri($procUri), $params);

                break;

            case MessageType::SUBSCRIBE:
                $topicUri = $this->getStringElement($message, 1, 'topic URI', allowNumeric: true);

                $this->middleware->onSubscribe($decoratedConnection, $this->getTopic($decoratedConnection, $topicUri));

                break;

            case MessageType::UNSUBSCRIBE:
                $topicUri = $this->getStringElement($message, 1, 'topic URI', allowNumeric: true);

                $this->middleware->onUnsubscribe($decoratedConnection, $this->getTopic($decoratedConnection, $topicUri));

                break;

            case MessageType::PUBLISH:
                $topicUri = $this->getStringElement($message, 1, 'topic URI', allowNumeric: true);

                // The event may be any JSON value, including null, so only its presence is validated
                if (!\array_key_exists(2, $message)) {
                    throw new InvalidMessage('Invalid "PUBLISH" message, the event payload is required.');
                }

                $event = $message[2];

                $exclude = $message[3] ?? false;

                if (true === $exclude) {
                    $sessionId = $decoratedConnection->getAttributeStore()->get(AttributeKey::WAMP_SESSION_ID);

                    $exclude = \is_string($sessionId) ? [$sessionId] : [];
                } elseif (false === $exclude) {
                    $exclude = [];
                } else {
                    $exclude = $this->getSessionIdList($exclude, 'exclude');
                }

                $eligible = $this->getSessionIdList($message[4] ?? [], 'eligible');

                $this->middleware->onPublish($decoratedConnection, $this->getTopic($decoratedConnection, $topicUri), $event, $exclude, $eligible);

                break;

            default:
                throw new UnsupportedMessageType($message[0], \sprintf('Unsupported WAMP message type "%s".', $message[0]));
        }
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        $decoratedConnection = $this->connections[$connection];
        $this->connections->offsetUnset($connection);

        $this->middleware->onClose($decoratedConnection);
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        if ($this->connections->offsetExists($connection)) {
            $this->middleware->onError($this->connections[$connection], $throwable);
        } else {
            $this->middleware->onError($connection, $throwable);
        }
    }

    public function getServerIdentity(): string
    {
        return $this->serverIdentity;
    }

    public function setServerIdentity(string $serverIdentity): void
    {
        $this->serverIdentity = $serverIdentity;
    }

    /**
     * @return positive-int
     */
    public function getMaxPrefixes(): int
    {
        return $this->maxPrefixes;
    }

    /**
     * @param positive-int $maxPrefixes
     */
    public function setMaxPrefixes(int $maxPrefixes): void
    {
        $this->maxPrefixes = $maxPrefixes;
    }

    /**
     * Gets a required, non-empty string element from a WAMP message.
     *
     * @param list<mixed> $message
     * @param bool        $allowNumeric Whether a numeric value is accepted and converted to a string
     *
     * @return non-empty-string
     *
     * @throws InvalidMessage if the element is missing or is not a non-empty string
     */
    private function getStringElement(array $message, int $index, string $name, bool $allowNumeric = false): string
    {
        $value = $message[$index] ?? null;

        if ($allowNumeric && (\is_int($value) || \is_float($value))) {
            $value = (string) $value;
        }

        if (!\is_string($value) || '' === $value) {
            throw new InvalidMessage(\sprintf('Invalid %s, must be a non-empty string.', $name));
        }

        return $value;
    }

    /**
     * Validates a list of WAMP session IDs from a "PUBLISH" message.
     *
     * @return list<string>
     *
     * @throws InvalidMessage if the value is not a list of strings
     */
    private function getSessionIdList(mixed $value, string $name): array
    {
        if (!\is_array($value) || !array_is_list($value)) {
            throw new InvalidMessage(\sprintf('Invalid "PUBLISH" message, the %s list must be a list of session IDs.', $name));
        }

        foreach ($value as $sessionId) {
            if (!\is_string($sessionId)) {
                throw new InvalidMessage(\sprintf('Invalid "PUBLISH" message, the %s list must only contain string session IDs.', $name));
            }
        }

        /* @var list<string> $value */
        return $value;
    }

    /**
     * Gets the topic for a URI from the registry, or creates a new topic if one is not registered.
     *
     * A new topic is intentionally not added to the registry, as this would allow clients to register an unlimited
     * number of topics; it is the responsibility of the {@see UpdateTopicSubscriptions} middleware to register the
     * topic once a connection has subscribed to it.
     */
    private function getTopic(WAMPConnection $connection, string $uri): Topic
    {
        $resolvedUri = $connection->getUri($uri);

        if ($this->topicRegistry->has($resolvedUri)) {
            return $this->topicRegistry->get($resolvedUri);
        }

        return new Topic($resolvedUri);
    }
}
