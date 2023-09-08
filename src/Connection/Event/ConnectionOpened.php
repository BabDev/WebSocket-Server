<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection\Event;

use BabDev\WebSocket\Server\Connection;

final class ConnectionOpened implements ConnectionAware
{
    public function __construct(private readonly Connection $connection) {}

    public function getConnection(): Connection
    {
        return $this->connection;
    }
}
