<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WebSocket;

use Ratchet\RFC6455\Messaging\MessageBuffer;

/**
 * The websocket connection context is a data object holding a reference to a connection and its message buffer.
 */
final readonly class WebSocketConnectionContext
{
    public function __construct(
        public WebSocketConnection $connection,
        public MessageBuffer $buffer,
    ) {}
}
