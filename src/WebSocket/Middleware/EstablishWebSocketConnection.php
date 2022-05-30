<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WebSocket\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\WebSocket\Exception\InvalidEncoding;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnection;
use BabDev\WebSocket\Server\WebSocket\WebSocketConnectionContext;
use BabDev\WebSocket\Server\WebSocketServerMiddleware;
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
     * @var callable
     * @phpstan-var callable(FrameInterface $frame, Connection $connection): void
     */
    private $pongReceiver;

    /**
     * @throws InvalidEncoding if UTF-8 support is not available
     */
    public function __construct(
        private readonly ServerMiddleware $middleware,
        private readonly NegotiatorInterface $negotiator = new ServerNegotiator(new RequestVerifier()),
    ) {
        if ('e29c93' !== bin2hex('✓')) {
            throw new InvalidEncoding('Invalid encoding, ensure the UTF-8 charset is active.');
        }

        $this->connections = new \SplObjectStorage();

        $this->negotiator->setStrictSubProtocolCheck(true);

        if ($this->middleware instanceof WebSocketServerMiddleware) {
            $this->negotiator->setSupportedSubProtocols($this->middleware->getSubProtocols());
        }

        $this->pongReceiver = static function (FrameInterface $frame, Connection $connection): void {};
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
            throw new MissingRequest(sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        $connection->getAttributeStore()->set('websocket.closing', false);

        $response = $this->negotiator->handshake($request);

        $connection->send(Message::toString($response));

        if (101 !== $response->getStatusCode()) {
            $connection->close();

            return;
        }

        $decoratedConnection = new WebSocketConnection($connection);

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
                        $pongReceiver = $this->pongReceiver;
                        $pongReceiver($frame, $decoratedConnection);

                        break;
                }
            },
        );

        $this->connections->attach($connection, new WebSocketConnectionContext($decoratedConnection, $buffer));

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
        if ($this->connections->contains($connection)) {
            $context = $this->connections[$connection];
            $this->connections->detach($connection);

            $this->middleware->onClose($context->connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        if ($this->connections->contains($connection)) {
            $this->middleware->onError($this->connections[$connection]->connection, $throwable);
        } else {
            $connection->close();
        }
    }

    public function setStrictSubProtocolCheck(bool $enable): void
    {
        $this->negotiator->setStrictSubProtocolCheck($enable);
    }

    public function enableKeepAlive(LoopInterface $loop, int $interval = 30): void
    {
        $lastPing = new Frame(uniqid(), true, Frame::OP_PING);

        /** @var \SplObjectStorage<Connection, null> $pingedConnections */
        $pingedConnections = new \SplObjectStorage();

        $splClearer = new \SplObjectStorage();

        $this->pongReceiver = static function (FrameInterface $frame, Connection $connection) use ($pingedConnections, &$lastPing): void {
            if ($frame->getPayload() === $lastPing->getPayload()) {
                $pingedConnections->detach($connection);
            }
        };

        $loop->addPeriodicTimer(
            $interval,
            function () use ($pingedConnections, &$lastPing, $splClearer): void {
                foreach ($pingedConnections as $pingedConnection) {
                    $pingedConnection->close();
                }

                $pingedConnections->removeAllExcept($splClearer);

                $lastPing = new Frame(uniqid(), true, Frame::OP_PING);

                foreach ($this->connections as $connection) {
                    $webSocketConnection = $this->connections[$connection]->connection;

                    $webSocketConnection->send($lastPing);
                    $pingedConnections->attach($webSocketConnection);
                }
            }
        );
    }
}
