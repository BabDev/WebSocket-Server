<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use Psr\Http\Message\RequestInterface;

/**
 * The server interface is the core entry point into the WebSocket server stack.
 *
 * A server is intended to run using a middleware stack of {@see ServerMiddleware} components. The server
 * middleware stack is composed of three levels:
 *
 * - Raw data middleware, represented by {@see RawDataServerMiddleware} - This middleware is intended to handle
 *   the raw incoming HTTP message when a connection is established with the WebSocket server; the last middleware
 *   in this portion of the stack *MUST* convert the message into a {@see RequestInterface} object and pass the message
 *   into a {@see RequestAwareServerMiddleware} instance
 * - Request aware middleware, represented by {@see RequestAwareServerMiddleware} - This middleware is intended
 *   to process the incoming HTTP message; the last middleware in this portion of the stack *MUST* trigger the
 *   appropriate controller for an action and execute its wrapping {@see MessageMiddleware}
 * - Message middleware, represented by {@see MessageMiddleware} - This middleware is intended to wrap a controller
 */
interface Server
{
    public function run(): void;
}
