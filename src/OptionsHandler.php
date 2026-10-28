<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * Reads and changes PHP ini settings in a way that tests can mock.
 */
interface OptionsHandler
{
    public function get(string $option): string|false;

    public function set(string $option, string|int|float|bool|null $value): string|false;
}
