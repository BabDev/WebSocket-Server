<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

/**
 * The server interface is the core entry point into the WebSocket server stack.
 *
 * A server is intended to run using a middleware stack of {@see Middleware} components. The server
 * middleware stack is composed of two levels:
 *
 * - Server middleware, represented by {@see ServerMiddleware} - This middleware is intended
 *   to process the incoming HTTP message and route it; the last middleware in this portion of the stack
 *   *MUST* trigger the appropriate {@see MessageHandler} for an action and execute its wrapping {@see MessageMiddleware}
 * - Message middleware, represented by {@see MessageMiddleware} - This middleware is intended
 *   to wrap a {@see MessageHandler} and allows acting on incoming WAMP messages on a per-message basis
 */
interface Server
{
    /**
     * A user agent string including the minor version for this package.
     *
     * This is used to identify the server when appropriate (i.e. the WAMP Welcome message).
     */
    final public const VERSION = 'BabDev-Websocket-Server/0.1';

    public function run(): void;
}
