<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Reader;

/**
 * The PHP session reader reads the raw session data using the internal "php_binary" format.
 *
 * This emulates the "php_binary" option for the `session.serialize_handler` configuration option.
 */
final class PhpBinaryReader implements Reader
{
    /**
     * @see https://www.php.net/manual/en/function.session-decode.php#108037
     */
    public function read(string $data): array
    {
        $deserialized = [];
        $offset = 0;

        while ($offset < \strlen($data)) {
            $num = \ord($data[$offset]);
            ++$offset;
            $variable = substr($data, $offset, $num);
            $offset += $num;
            $deserializedSection = unserialize(substr($data, $offset));

            $deserialized[$variable] = $deserializedSection;
            $offset += \strlen(serialize($deserializedSection));
        }

        return $deserialized;
    }
}
