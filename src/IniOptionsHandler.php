<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

final class IniOptionsHandler implements OptionsHandler
{
    public function get(string $option): string|false
    {
        return \ini_get($option);
    }

    public function set(string $option, string|int|float|bool|null $value): string|false
    {
        return \ini_set($option, $value);
    }
}
