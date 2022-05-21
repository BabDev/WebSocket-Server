<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Connection\AttributeStoreInterface;

/**
 * The connection interface provides access to a connection resource and an attribute store for each connected client
 * to the WebSocket server.
 */
interface ConnectionInterface
{
    public function getAttributeStore(): AttributeStoreInterface;

    public function getConnection(): mixed;

    public function send(string $data): void;

    public function close(): void;
}
