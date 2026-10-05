<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;

/**
 * The PHP session reader reads the raw session data using the internal "php_binary" format.
 *
 * This emulates the "php_binary" option for the `session.serialize_handler` configuration option.
 */
final class PhpBinaryReader implements Reader
{
    /**
     * @throws InvalidSession if the session data cannot be deserialized
     */
    public function read(string $data): array
    {
        $deserialized = [];
        $offset = 0;
        $length = \strlen($data);

        while ($offset < $length) {
            // Each variable starts with a single byte holding the length of its name
            $nameLength = \ord($data[$offset]);
            ++$offset;

            if ($offset + $nameLength > $length) {
                throw new InvalidSession($data, 'Cannot deserialize session data.');
            }

            $name = substr($data, $offset, $nameLength);
            $offset += $nameLength;

            $serializedLength = SerializedValue::getLength(substr($data, $offset));

            if (false === $serializedLength) {
                throw new InvalidSession($data, 'Cannot deserialize session data.');
            }

            $deserialized[$name] = SerializedValue::unserialize(substr($data, $offset, $serializedLength), $data);

            $offset += $serializedLength;
        }

        return $deserialized;
    }
}
