<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

final class IniOptionsHandler implements OptionsHandler
{
    /**
     * @phpstan-return string|false
     */
    public function get(string $option): mixed
    {
        return \ini_get($option);
    }

    /**
     * @phpstan-param string|int|float|bool|null $value
     */
    public function set(string $option, mixed $value): void
    {
        ini_set($option, $value);
    }
}
