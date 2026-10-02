# Restrict To Allowed Origins Middleware

The `BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which can be used to reject connections based on the `Origin` header from the HTTP request.

The allowed origin list can be updated at any time, including while the server is running.

## Allowed Origin Formats

An allowed origin can be given in one of two formats:

- A full origin, such as `https://babdev.com` or `http://localhost:8080`, which only allows connections whose `Origin` header has the same scheme, host, and port; the default port for the `http`, `https`, `ws`, and `wss` schemes may be omitted, so `https://babdev.com` and `https://babdev.com:443` are equivalent
- A host, such as `babdev.com`, which allows connections whose `Origin` header has the same host with any scheme and port

Hosts are compared case-insensitively, and a host does not match its subdomains (`babdev.com` does not allow `https://www.babdev.com`).

Using a full origin is recommended, as a host also allows connections from pages served over an insecure `http` scheme or from another port on the same host.

A `BabDev\WebSocket\Server\Http\Exception\InvalidAllowedOrigin` exception is thrown for an allowed origin which is not in one of these formats, such as a host with a port (`localhost:8080`) or an origin with a path (`https://babdev.com/path`).

## Allowing an Origin

To allow an origin, you can use the middleware's `allowOrigin()` method:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;

$middleware = new RestrictToAllowedOrigins($decoratedMiddleware);
$middleware->allowOrigin('https://babdev.com');
$middleware->allowOrigin('localhost');
```

## Removing a Previously Allowed Origin

To remove a previously allowed origin, you can use the middleware's `removeAllowedOrigin()` method; the origin can be given in any equivalent form (for example, `https://babdev.com:443` removes `https://babdev.com`):

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
