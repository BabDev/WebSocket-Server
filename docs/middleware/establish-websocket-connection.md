# Establish WebSocket Connection Middleware

The `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is used to establish the client websocket connection by sending the appropriate HTTP response.

The middleware also allows enabling a keepalive ping-pong for the server.

## Enabling keepalive

To enable the keepalive feature, you can use the middleware's `enableKeepAlive()` method, providing it the event loop and the interval time in seconds (defaults to 30):

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use React\EventLoop\Loop;

$middleware = new EstablishWebSocketConnection($decoratedMiddleware);
$middleware->enableKeepAlive(Loop::get(), 60);
```

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere after the HTTP request has been parsed and does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage` middleware, but it can decorate any server middleware.
