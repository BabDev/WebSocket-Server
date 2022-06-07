<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

/**
 * The PHP session reader reads the raw session data using the internal "php_serialize" format.
 *
 * This emulates the "php_serialize" option for the `session.serialize_handler` configuration option.
 */
final class PhpSerializeReader implements Reader
{
    public function read(string $data): array
    {
        return unserialize($data);
    }
}
