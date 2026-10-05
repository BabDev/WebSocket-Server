<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;

/**
 * @internal
 */
final class SerializedValue
{
    /**
     * Gets the length of the serialized value at the start of the data.
     *
     * References ("r" and "R" values) are not supported, as their indexes refer to the whole session payload and would be
     * resolved incorrectly when each variable is deserialized separately.
     *
     * @return int|false The length of the serialized value, or false if a value cannot be read
     */
    public static function getLength(string $data): int|false
    {
        if ('' === $data) {
            return false;
        }

        switch ($data[0]) {
            // Null value
            case 'N':
                return str_starts_with($data, 'N;') ? 2 : false;

                // Boolean value
            case 'b':
                return 1 === preg_match('/^b:[01];/', $data) ? 4 : false;

                // Integer or floating point value
            case 'i':
            case 'd':
                $end = strpos($data, ';');

                return false === $end ? false : $end + 1;

                // String value
            case 's':
                if (1 !== preg_match('/^s:(\d+):"/', $data, $matches)) {
                    return false;
                }

                // Add characters for the closing quote and semicolon
                $length = \strlen($matches[0]) + (int) $matches[1] + 2;

                return $length <= \strlen($data) && '";' === substr($data, $length - 2, 2) ? $length : false;

                // Enum
            case 'E':
                if (1 !== preg_match('/^E:\d+:"[^"]+";/', $data, $matches)) {
                    return false;
                }

                return \strlen($matches[0]);

                // Object using the Serializable interface
            case 'C':
                if (1 !== preg_match('/^C:\d+:"[^"]+":(\d+):\{/', $data, $matches)) {
                    return false;
                }

                // Add a character for the closing brace
                $length = \strlen($matches[0]) + (int) $matches[1] + 1;

                return $length <= \strlen($data) && '}' === $data[$length - 1] ? $length : false;

                // Array or object value
            case 'a':
            case 'O':
                $isArray = 'a' === $data[0];

                $pattern = $isArray ? '/^a:(\d+):\{/' : '/^O:\d+:"[^"]+":(\d+):\{/';

                if (1 !== preg_match($pattern, $data, $matches)) {
                    return false;
                }

                $count = (int) $matches[1];
                $offset = \strlen($matches[0]);
                $length = \strlen($data);

                // Double the count to account for each element having a key and value
                for ($i = 0; $i < $count * 2; ++$i) {
                    $segmentLength = self::getLength(substr($data, $offset));

                    if (false === $segmentLength) {
                        return false;
                    }

                    $offset += $segmentLength;

                    if ($offset >= $length) {
                        return false;
                    }
                }

                if ('}' !== $data[$offset]) {
                    return false;
                }

                // Add a character for the closing brace
                return $offset + 1;

                // Unsupported value
            default:
                return false;
        }
    }

    /**
     * Deserializes a single serialized value from the session data.
     *
     * @param string $serialized  The serialized value
     * @param string $sessionData The full session data, used for the exception if the value cannot be deserialized
     *
     * @throws InvalidSession if the value cannot be deserialized
     */
    public static function unserialize(string $serialized, string $sessionData): mixed
    {
        // unserialize() reports invalid data with a warning or notice, which is converted to an exception
        set_error_handler(
            static function (int $errno, string $errstr) use ($sessionData): never {
                throw new InvalidSession($sessionData, \sprintf('Cannot deserialize session data: %s', $errstr));
            },
            \E_WARNING | \E_NOTICE,
        );

        try {
            $value = unserialize($serialized);
        } finally {
            restore_error_handler();
        }

        if (false === $value && 'b:0;' !== $serialized) {
            throw new InvalidSession($sessionData, 'Cannot deserialize session data.');
        }

        return $value;
    }
}
