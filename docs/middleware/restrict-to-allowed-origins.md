# Reject Blocked IP Address Middleware

The `BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which can be used to reject connections based on the `Origin` header from the HTTP request.

The allowed origin list can be updated at any time, including while the server is running.

## Allowing an Origin

To allow a origin, you can use the middleware's `allowOrigin()` method:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;

$middleware = new RestrictToAllowedOrigins($decoratedMiddleware);
$middleware->allowOrigin('babdev.com');
```

## Removing a Previously Allowed Origin

To allow a previously blocked IP address, you can use the middleware's `removeAllowedOrigin()` method:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;

$middleware = new RestrictToAllowedOrigins($decoratedMiddleware);
$middleware->allowOrigin('babdev.com');
$middleware->allowOrigin('github.com');
$middleware->removeAllowedOrigin('github.com');
```

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere after the HTTP request has been parsed and does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` middleware, but it can decorate any server middleware.
