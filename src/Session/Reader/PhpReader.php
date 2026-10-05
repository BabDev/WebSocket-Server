<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;

/**
 * The PHP session reader reads the raw session data using the internal "php" format.
 *
 * This emulates the "php" option for the `session.serialize_handler` configuration option.
 */
final class PhpReader implements Reader
{
    private const string DELIMITER = '|';

    /**
     * @throws InvalidSession if the session data cannot be deserialized
     */
    public function read(string $data): array
    {
        $deserialized = [];
        $offset = 0;

        while ($offset < \strlen($data)) {
            $currentPos = strpos($data, self::DELIMITER, $offset);

            if (false === $currentPos) {
                throw new InvalidSession($data, 'Cannot deserialize session data.');
            }

            $name = substr($data, $offset, $currentPos - $offset);
            $offset = $currentPos + 1;

            // Find the position for the end of the serialized data so we can correctly chop the next variable if need be
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
