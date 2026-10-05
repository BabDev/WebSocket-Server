# Establish WebSocket Connection Middleware

The `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is used to establish the client websocket connection by sending the appropriate HTTP response.

The middleware also allows enabling a keepalive ping-pong for the server.

## Sub-Protocol Negotiation

When the decorated middleware supports WebSocket sub-protocols (the `BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage` middleware supports the `wamp` sub-protocol), the middleware negotiates the sub-protocol with the client during the handshake. By default, the check is strict: a client which does not request a supported sub-protocol in its `Sec-WebSocket-Protocol` header, including a client which does not request any sub-protocol, is rejected with a `426 Upgrade Required` response.

The strict check can be disabled with the middleware's `setStrictSubProtocolCheck()` method, which allows clients to connect without requesting a supported sub-protocol:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;

$middleware = new EstablishWebSocketConnection($decoratedMiddleware);
$middleware->setStrictSubProtocolCheck(false);
```

## Enabling keepalive

The keepalive sends a ping to every connected client at the given interval, and closes connections which have not responded to the previous ping by the time the next one is sent. This removes connections whose client has disappeared without closing the connection (for example, after a network failure).

To enable the keepalive feature, you can use the middleware's `enableKeepAlive()` method, providing it the event loop and the interval time in seconds (defaults to 30); calling the method again replaces the previous interval:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use React\EventLoop\Loop;

$middleware = new EstablishWebSocketConnection($decoratedMiddleware);
$middleware->enableKeepAlive(Loop::get(), 60);
```

When using the `BabDev\WebSocket\Server\Application` class, the keepalive is disabled by default and can be enabled with the `withKeepAlive()` method.

## Message Size Limits

To help prevent a client from exhausting the server's memory, the middleware can limit the size of the messages received from clients. A connection sending a message or frame over the limit is closed with a "1009 Message Too Big" close frame.

The limits are set with the third and fourth arguments to the middleware's constructor, or by updating the public `$maxMessagePayloadSize` and `$maxFramePayloadSize` properties (changes apply to connections opened afterwards):

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;

$middleware = new EstablishWebSocketConnection(
    $decoratedMiddleware,
    maxMessagePayloadSize: 1_048_576, // 1 MiB for a complete message
    maxFramePayloadSize: 65_536, // 64 KiB for a single frame
);
```

When a limit is `null` (the default), the default of the `ratchet/rfc6455` package is used, which is a quarter of the `memory_limit` setting. This means there is no limit when the memory limit is disabled (`memory_limit = -1`, which is common for long-running CLI processes), so setting explicit limits is recommended. A limit of `0` disables the limit.

## Closing All Connections

The middleware's `closeAllConnections()` method closes every established WebSocket connection with a close frame, using the "1001 Going Away" status code by default. This is used to close connections cleanly when the server is shutting down (see the [graceful shutdown](/open-source/packages/websocket-server/docs/1.x/architecture#graceful-shutdown) section from the architecture documentation).

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere after the HTTP request has been parsed and does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage` middleware, but it can decorate any server middleware.
