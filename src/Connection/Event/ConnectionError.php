<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection\Event;

use BabDev\WebSocket\Server\Connection;

final readonly class ConnectionError implements ConnectionAware
{
    public function __construct(
        private Connection $connection,
        private \Throwable $throwable,
    ) {}

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function getThrowable(): \Throwable
    {
        return $this->throwable;
    }
}
