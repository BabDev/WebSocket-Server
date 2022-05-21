<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server interface is the core entry point into the WebSocket server stack.
 */
interface ServerInterface
{
    public function run(): void;
}
