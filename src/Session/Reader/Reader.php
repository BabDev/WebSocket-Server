<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;

/**
 * The session reader interface defines an object which can read the raw session data.
 */
interface Reader
{
    /**
     * @throws InvalidSession if the session data cannot be deserialized
     */
    public function read(string $data): array;
}
