<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection\Event;

use BabDev\WebSocket\Server\Connection;

final class ConnectionError implements ConnectionAware
{
    public function __construct(
        private readonly Connection $connection,
        private readonly \Throwable $throwable,
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
