<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

use BabDev\WebSocket\Server\ConnectionInterface;
use React\Socket\ConnectionInterface as ReactSocketConnectionInterface;

final class ReactSocketConnection implements ConnectionInterface
{
    public function __construct(
        private readonly ReactSocketConnectionInterface $connection,
        private readonly AttributeStoreInterface $attributeStore,
    ) {
    }

    public function getAttributeStore(): AttributeStoreInterface
    {
        return $this->attributeStore;
    }

    public function getConnection(): ReactSocketConnectionInterface
    {
        return $this->connection;
    }

    public function send(string $data): void
    {
        $this->connection->write($data);
    }

    public function close(): void
    {
        $this->connection->end();
    }
}
