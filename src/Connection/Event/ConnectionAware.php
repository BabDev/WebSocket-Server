<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection\Event;

use BabDev\WebSocket\Server\Connection;

/**
 * A connection aware event is an event emitted with the client {@see Connection} instance.
 */
interface ConnectionAware
{
    public function getConnection(): Connection;
}
