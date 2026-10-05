<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;

/**
 * The PHP session reader reads the raw session data using the internal "php_serialize" format.
 *
 * This emulates the "php_serialize" option for the `session.serialize_handler` configuration option.
 */
final class PhpSerializeReader implements Reader
{
    /**
     * @throws InvalidSession if the session data cannot be deserialized
     */
    public function read(string $data): array
    {
        $deserialized = SerializedValue::unserialize($data, $data);

        if (!\is_array($deserialized)) {
            throw new InvalidSession($data, \sprintf('Cannot deserialize session data, the "php_serialize" format must contain an array, %s given.', get_debug_type($deserialized)));
        }

        return $deserialized;
    }
}
