<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server interface is the core entry point into the WebSocket server stack.
 *
 * A server is intended to run using a middleware stack of {@see ServerMiddleware} components. The server
 * middleware stack is composed of two levels:
 *
 * - Server middleware, represented by {@see RequestAwareServerMiddleware} - This middleware is intended
 *   to process the incoming HTTP message and route it; the last middleware in this portion of the stack
     *MUST* trigger the appropriate controller for an action and execute its wrapping {@see MessageMiddleware}
 * - Message middleware, represented by {@see MessageMiddleware} - This middleware is intended to wrap a controller
 */
interface Server
{
    public function run(): void;
}
