# Dispatch Message To Handler Middleware

The `BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is responsible for routing the WAMP message to its [message handler](/open-source/packages/websocket-server/docs/1.x/message-handler).

This middleware also supports emitting events using a [PSR-14](https://www.php-fig.org/psr/psr-14/) compatible event dispatcher when a connection is opened, closed, or an error occurs.

This middleware requires the Symfony's Routing component to serve as the websocket server's router.

Please see the [Symfony documentation](https://symfony.com/doc/current/routing.html) for more information on how to use their API.

## RPC Error Handling

Per the [WAMP v1](https://web.archive.org/web/20150419051041/http://wamp.ws/spec/wamp1/) specification, a client waits for a "CALLRESULT" or "CALLERROR" message in response to each "CALL" message. To ensure the client always receives a response, the middleware sends a "CALLERROR" message when the call cannot be completed:

- When no route matches the procedure URI, or a message handler cannot be resolved, the error uses the `not-found` error type with a `404` code
- When the resolved message handler is not an RPC handler, or the handler throws while processing the call, the error uses the `internal-error` error type with a `500` code

For internal errors, the message from the exception is not sent to the client to avoid disclosing information about the server. After the "CALLERROR" message is sent, the exception is rethrown so it can be handled by the server's error handling (including the `ConnectionError` event below).

A message handler which sends its own "CALLERROR" message for an error should do so instead of throwing, otherwise the client will receive a second "CALLERROR" message for the same call.

## Available Events

- `BabDev\WebSocket\Server\Connection\Event\ConnectionClosed` - dispatched when a client has closed their connection
- `BabDev\WebSocket\Server\Connection\Event\ConnectionError` - dispatched when there is a client error or an unhandled exception on the server
- `BabDev\WebSocket\Server\Connection\Event\ConnectionOpened` - dispatched when a new client has connected to the server

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be decorated by any WAMP server middleware.

This middleware is intended to be the innermost middleware in your application, and as such, does not support decorating other middleware.
