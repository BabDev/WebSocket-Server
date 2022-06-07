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
     *
     * @see https://www.php.net/manual/en/function.session-decode.php#108037
     */
    public function read(string $data): array
    {
        $deserialized = [];
        $offset = 0;

        while ($offset < \strlen($data)) {
            if (!str_contains(substr($data, $offset), self::DELIMITER)) {
                throw new InvalidSession($data, 'Cannot deserialize session data.');
            }

            $pos = strpos($data, self::DELIMITER, $offset);
            $num = $pos - $offset;
            $variable = substr($data, $offset, $num);
            $offset += $num + 1;
            $deserializedSection = unserialize(substr($data, $offset));

            $deserialized[$variable] = $deserializedSection;
            $offset += \strlen(serialize($deserializedSection));
        }

        return $deserialized;
    }
}
