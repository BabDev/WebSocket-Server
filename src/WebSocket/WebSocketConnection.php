<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WebSocket;

use BabDev\WebSocket\Server\Connection;
use Ratchet\RFC6455\Messaging\DataInterface;

/**
 * The websocket connection interface extends the {@see Connection} to add support for
 * processing messages using the `ratchet/rfc6455` package.
 */
interface WebSocketConnection extends Connection
{
    public function send(string|DataInterface $data): void;

    public function close(mixed $data = 1000): void;
}
