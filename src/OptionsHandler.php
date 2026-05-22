<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The options handler interface provides an abstraction layer to the PHP `ini_get()` and `ini_set()` functions.
 */
interface OptionsHandler
{
    public function get(string $option): string|false;

    public function set(string $option, string|int|float|bool|null $value): string|false;
}
