<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

final class IniOptionsHandler implements OptionsHandler
{
    public function get(string $option): mixed
    {
        return \ini_get($option);
    }

    public function set(string $option, mixed $value): void
    {
        ini_set($option, $value);
    }
}
