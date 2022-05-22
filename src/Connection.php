<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Connection\AttributeStore;

/**
 * The connection interface provides access to a connection resource and an attribute store for each connected client
 * to the WebSocket server.
 */
interface Connection
{
    public function getAttributeStore(): AttributeStore;

    public function getConnection(): mixed;

    public function send(string $data): void;

    public function close(): void;
}
