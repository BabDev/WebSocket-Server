<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

use BabDev\WebSocket\Server\Connection;
use React\Socket\ConnectionInterface;

/**
 * The React socket connection is a connection class wrapping a {@see ConnectionInterface}
 * from the `react/socket` package.
 */
final class ReactSocketConnection implements Connection
{
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly AttributeStore $attributeStore,
    ) {}

    public function getAttributeStore(): AttributeStore
    {
        return $this->attributeStore;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function send(string $data): void
    {
        $this->connection->write($data);
    }

    public function close(mixed $data = null): void
    {
        $this->connection->end($data);
    }
}
