<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WebSocket\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\WebSocket\DefaultWebSocketConnection;
use BabDev\WebSocket\Server\WebSocket\Exception\InvalidEncoding;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnectionContext;
use BabDev\WebSocket\Server\WebSocketServerMiddleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;
use Ratchet\RFC6455\Handshake\NegotiatorInterface;
use Ratchet\RFC6455\Handshake\RequestVerifier;
use Ratchet\RFC6455\Handshake\ServerNegotiator;
use Ratchet\RFC6455\Messaging\CloseFrameChecker;
use Ratchet\RFC6455\Messaging\Frame;
use Ratchet\RFC6455\Messaging\FrameInterface;
use Ratchet\RFC6455\Messaging\MessageBuffer;
use Ratchet\RFC6455\Messaging\MessageInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

/**
 * The establish websocket connection server middleware sends the HTTP response for the connection to establish the
 * websocket connection and processes the keepalive ping-pong messages when the feature is enabled.
 */
final class EstablishWebSocketConnection implements ServerMiddleware
{
    /**
     * @var \SplObjectStorage<Connection, WebSocketConnectionContext>
     */
    private readonly \SplObjectStorage $connections;

    /**
     * @var \SplObjectStorage<WebSocketConnection, null>
     */
    private readonly \SplObjectStorage $pingedConnections;

    private ?Frame $lastPing = null;

    private ?LoopInterface $keepAliveLoop = null;

    private ?TimerInterface $keepAliveTimer = null;

    /**
     * @param int<0, max>|null $maxMessagePayloadSize
     * @param int<0, max>|null $maxFramePayloadSize
     *
     * @throws InvalidEncoding if UTF-8 support is not available
     */
    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly NegotiatorInterface $negotiator = new ServerNegotiator(new RequestVerifier(), new HttpFactory()),
        public ?int $maxMessagePayloadSize = null,
        public ?int $maxFramePayloadSize = null,
    ) {
        if ('e29c93' !== bin2hex('✓')) {
            throw new InvalidEncoding('Invalid encoding, ensure the UTF-8 charset is active.');
        }

        $this->connections = new \SplObjectStorage();
        $this->pingedConnections = new \SplObjectStorage();

        $this->negotiator->setStrictSubProtocolCheck(true);

        if ($this->middleware instanceof WebSocketServerMiddleware) {
            $this->negotiator->setSupportedSubProtocols($this->middleware->getSubProtocols());
        }
    }

    /**
     * Handles a new connection to the server.
     *
     * @throws MissingRequest if the HTTP request has not been parsed before this middleware is executed
     */
    public function onOpen(Connection $connection): void
    {
        /** @var RequestInterface|null $request */
        $request = $connection->getAttributeStore()->get('http.request');

        if (!$request instanceof RequestInterface) {
            throw new MissingRequest(\sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        $connection->getAttributeStore()->set('websocket.closing', false);

        $response = $this->negotiator->handshake($request)
            ->withoutHeader('X-Powered-By');

        $connection->send(Message::toString($response));

        if (101 !== $response->getStatusCode()) {
            $connection->close();

            return;
        }

        $decoratedConnection = new DefaultWebSocketConnection($connection);

        $buffer = new MessageBuffer(
            new CloseFrameChecker(),
            function (MessageInterface $message) use ($decoratedConnection): void {
                $this->middleware->onMessage($decoratedConnection, $message->getPayload());
            },
            function (FrameInterface $frame) use ($decoratedConnection): void {
                switch ($frame->getOpCode()) {
                    case Frame::OP_CLOSE:
                        $decoratedConnection->close($frame);

                        break;

                    case Frame::OP_PING:
                        $decoratedConnection->send(new Frame($frame->getPayload(), true, Frame::OP_PONG));

                        break;

                    case Frame::OP_PONG:
                        $this->onPong($frame, $decoratedConnection);

                        break;
                }
            },
            maxMessagePayloadSize: $this->maxMessagePayloadSize,
            maxFramePayloadSize: $this->maxFramePayloadSize,
        );

        $this->connections->offsetSet($connection, new WebSocketConnectionContext($decoratedConnection, $buffer));

        $this->middleware->onOpen($decoratedConnection);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        if (true === $connection->getAttributeStore()->get('websocket.closing', false)) {
            return;
        }

        $this->connections[$connection]->buffer->onData($data);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        if ($this->connections->offsetExists($connection)) {
            $context = $this->connections[$connection];
            $this->connections->offsetUnset($connection);
            $this->pingedConnections->offsetUnset($context->connection);

            $this->middleware->onClose($context->connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        if ($this->connections->offsetExists($connection)) {
            $this->middleware->onError($this->connections[$connection]->connection, $throwable);
        } else {
            $this->middleware->onError($connection, $throwable);
        }
    }

    /**
     * Closes all established WebSocket connections with a close frame.
     */
    public function closeAllConnections(int $code = Frame::CLOSE_GOING_AWAY): void
    {
        // Closing a connection can synchronously trigger onClose(), so the connections are copied before iterating
        $openConnections = [];

        foreach ($this->connections as $connection) {
            $openConnections[] = $this->connections[$connection]->connection;
        }

        foreach ($openConnections as $webSocketConnection) {
            $webSocketConnection->close($code);
        }
    }

    public function setStrictSubProtocolCheck(bool $enable): void
    {
        $this->negotiator->setStrictSubProtocolCheck($enable);
    }

    /**
     * Enables the keepalive ping-pong, closing connections which do not respond to a ping before the next one is sent.
     *
     * Calling this method again replaces the previous keepalive timer.
     *
     * @param positive-int $interval The number of seconds between pings
     */
    public function enableKeepAlive(LoopInterface $loop, int $interval = 30): void
    {
        if ($this->keepAliveTimer instanceof TimerInterface) {
            $this->keepAliveLoop?->cancelTimer($this->keepAliveTimer);
        }

        $this->keepAliveLoop = $loop;
        $this->keepAliveTimer = $loop->addPeriodicTimer($interval, $this->sendKeepAlivePings(...));
    }

    private function onPong(FrameInterface $frame, WebSocketConnection $connection): void
    {
        if ($this->lastPing instanceof Frame && $frame->getPayload() === $this->lastPing->getPayload()) {
            $this->pingedConnections->offsetUnset($connection);
        }
    }

    private function sendKeepAlivePings(): void
    {
        // Closing a connection can synchronously trigger onClose(), so the storage objects are copied before iterating
        $unresponsiveConnections = iterator_to_array($this->pingedConnections, false);

        foreach ($unresponsiveConnections as $unresponsiveConnection) {
            $this->pingedConnections->offsetUnset($unresponsiveConnection);
            $unresponsiveConnection->close();
        }

        $this->lastPing = new Frame(uniqid(), true, Frame::OP_PING);

        $openConnections = [];

        foreach ($this->connections as $connection) {
            $openConnections[] = $this->connections[$connection]->connection;
        }

        foreach ($openConnections as $webSocketConnection) {
            $webSocketConnection->send($this->lastPing);
            $this->pingedConnections->offsetSet($webSocketConnection);
        }
    }
}
