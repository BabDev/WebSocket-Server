# Dispatch Message To Handler Middleware

The `BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is responsible for routing the WAMP message to its [message handler](/open-source/packages/websocket-server/docs/1.x/message-handler).

This middleware also supports emitting events using a [PSR-14](https://www.php-fig.org/psr/psr-14/) compatible event dispatcher when a connection is opened, closed, or an error occurs.

This middleware requires the Symfony's Routing component to serve as the websocket server's router.

Please see the [Symfony documentation](https://symfony.com/doc/current/routing.html) for more information on how to use their API.

## Available Events

- `BabDev\WebSocket\Server\Connection\Event\ConnectionClosed` - dispatched when a client has closed their connection
- `BabDev\WebSocket\Server\Connection\Event\ConnectionError` - dispatched when there is a client error or an unhandled exception on the server
- `BabDev\WebSocket\Server\Connection\Event\ConnectionOpened` - dispatched when a new client has connected to the server

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\WebSocket\Middleware\UpdateTopicSubscriptions` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be decorated by any WAMP server middleware.

This middleware is intended to be the innermost middleware in your application, and as such, does not support decorating other middleware.
