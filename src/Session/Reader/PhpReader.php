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
    private const DELIMITER = '|';

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
            $serializedLength = $this->getSerializedSegmentLength(substr($data, $offset));

            if (false === $serializedLength) {
                throw new InvalidSession($data, 'Cannot deserialize session data.');
            }

            $rawData = substr($data, $offset, $serializedLength);

            $value = unserialize($rawData);

            $deserialized[$name] = $value;

            $offset += $serializedLength;
        }

        return $deserialized;
    }

    private function getSerializedSegmentLength(string $data): int|false
    {
        // No serialized value can have a length of less than 4 characters
        if (\strlen($data) < 4) {
            return false;
        }

        // The data type will be in position 0
        switch ($data[0]) {
            // Null value
            case 'N':
                return 2;

                // Boolean value
            case 'b':
                return 4;

                // Integer or floating point value
            case 'i':
            case 'd':
                $end = strpos($data, ';');

                return false === $end ? false : $end + 1;

                // String value
            case 's':
                if (!preg_match('/^s:\d+:"/', $data, $matches)) {
                    return false;
                }

                // Add characters for the closing quote and semicolon
                return \strlen($matches[0]) + (int) substr($matches[0], 2, -2) + 2;

                // Enum
            case 'E':
                if (!preg_match('/^E:\d+:"[^"]+";/', $data, $matches)) {
                    return false;
                }

                return strlen($matches[0]);

                // Array or object value
            case 'a':
            case 'O':
                $isArray = 'a' === $data[0];

                $pattern = $isArray ? '/^a:\d+:\{/' : '/^O:\d+:"[^"]+":(\d+):\{/';

                if (!preg_match($pattern, $data, $matches)) {
                    return false;
                }

                $start = \strlen($matches[0]);
                $count = $isArray ? (int) substr($matches[0], 2, -2) : (int) $matches[1];
                $offset = $start;
                $length = \strlen($data);

                // Double the count to account for each element having a key and value
                for ($i = 0; $i < $count * 2; ++$i) {
                    $segmentLength = $this->getSerializedSegmentLength(substr($data, $offset));

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

                // Add characters for the closing brace
                return $offset + 1;

                // Unsupported value
            default:
                return false;
        }
    }
}
