<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Server;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessage;
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
    private const WAMP_PROTOCOL_VERSION = 1;

    /**
     * @var \SplObjectStorage<Connection, WAMPConnection>
     */
    private readonly \SplObjectStorage $connections;

    public string $serverIdentity = Server::VERSION;

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
        return array_merge($this->middleware->getSubProtocols(), ['wamp']);
    }

    /**
     * Handles a new connection to the server.
     *
     * @throws InvalidMessage if the welcome message cannot be JSON encoded
     */
    public function onOpen(Connection $connection): void
    {
        $decoratedConnection = new WAMPConnection($connection);
        $decoratedConnection->getAttributeStore()->set('wamp.session_id', $sessionId = bin2hex(random_bytes(32)));
        $decoratedConnection->getAttributeStore()->set('wamp.prefixes', []);

        try {
            $decoratedConnection->send(json_encode([MessageType::WELCOME, $sessionId, self::WAMP_PROTOCOL_VERSION, $this->serverIdentity], \JSON_THROW_ON_ERROR));
        } catch (\JsonException $exception) {
            throw new InvalidMessage($exception->getMessage(), $exception->getCode(), $exception);
        }

        $this->connections->attach($connection, $decoratedConnection);

        $this->middleware->onOpen($decoratedConnection);
    }

    /**
     * Handles incoming data on the connection.
     *
     * @throws InvalidMessage         if the WAMP message is badly formatted or contains invalid data
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

        if (!\is_array($message) || $message !== array_values($message)) {
            throw new InvalidMessage('Invalid WAMP message format.');
        }

        if (isset($message[1]) && !(\is_string($message[1]) || is_numeric($message[1]))) {
            throw new InvalidMessage('Invalid Topic, must be a string.');
        }

        switch ($message[0]) {
            case MessageType::PREFIX:
                /** @var array<string, string> $prefixes */
                $prefixes = $decoratedConnection->getAttributeStore()->get('wamp.prefixes', []);
                $prefixes[$message[1]] = $message[2];

                $decoratedConnection->getAttributeStore()->set('wamp.prefixes', $prefixes);

                break;

            case MessageType::CALL:
                array_shift($message);
                $callID = array_shift($message);
                $procURI = array_shift($message);

                if (1 == \count($message) && \is_array($message[0])) {
                    $message = $message[0];
                }

                $resolvedUri = $decoratedConnection->getUri($procURI);

                if ($this->topicRegistry->has($resolvedUri)) {
                    $topic = $this->topicRegistry->get($resolvedUri);
                } else {
                    $this->topicRegistry->add($topic = new Topic($resolvedUri));
                }

                $this->middleware->onCall($decoratedConnection, $callID, $topic, $message);

                break;

            case MessageType::SUBSCRIBE:
                $resolvedUri = $decoratedConnection->getUri($message[1]);

                if ($this->topicRegistry->has($resolvedUri)) {
                    $topic = $this->topicRegistry->get($resolvedUri);
                } else {
                    $this->topicRegistry->add($topic = new Topic($resolvedUri));
                }

                $this->middleware->onSubscribe($decoratedConnection, $topic);

                break;

            case MessageType::UNSUBSCRIBE:
                $resolvedUri = $decoratedConnection->getUri($message[1]);

                if ($this->topicRegistry->has($resolvedUri)) {
                    $topic = $this->topicRegistry->get($resolvedUri);
                } else {
                    $this->topicRegistry->add($topic = new Topic($resolvedUri));
                }

                $this->middleware->onUnsubscribe($decoratedConnection, $topic);

                break;

            case MessageType::PUBLISH:
                $exclude = $message[3] ?? null;

                if (!\is_array($exclude)) {
                    if (true === (bool) $exclude) {
                        $sessionId = $decoratedConnection->getAttributeStore()->get('wamp.session_id');
                        $exclude = [$sessionId];
                    } else {
                        $exclude = [];
                    }
                }

                $eligible = $message[4] ?? [];

                $resolvedUri = $decoratedConnection->getUri($message[1]);

                if ($this->topicRegistry->has($resolvedUri)) {
                    $topic = $this->topicRegistry->get($resolvedUri);
                } else {
                    $this->topicRegistry->add($topic = new Topic($resolvedUri));
                }

                $this->middleware->onPublish($decoratedConnection, $topic, $message[2], $exclude, $eligible);

                break;

            default:
                throw new UnsupportedMessageType($message[0], sprintf('Unsupported WAMP message type "%s".', $message[0]));
        }
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        $decoratedConnection = $this->connections[$connection];
        $this->connections->detach($connection);

        $this->middleware->onClose($decoratedConnection);
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->middleware->onError($this->connections[$connection], $throwable);
    }
}
