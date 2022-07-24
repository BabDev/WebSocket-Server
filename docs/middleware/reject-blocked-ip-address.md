# Reject Blocked IP Address Middleware

The `BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which can be used to reject connections from blocked IP addresses.

The middleware supports blocking either single addresses or subnet ranges from both IPv4 and IPv6 network ranges.

The blocked address list can be updated at any time, including while the server is running.

## Blocking an Address

To block an IP address, you can use the middleware's `blockAddress()` method:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;

$middleware = new RejectBlockedIpAddress($decoratedMiddleware);
$middleware->blockAddress('192.168.1.1');
$middleware->blockAddress('192.168.1.0/24');
$middleware->blockAddress('::1');
```

## Allowing a Previously Blocked Address

To allow a previously blocked IP address, you can use the middleware's `allowAddress()` method:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;

$middleware = new RejectBlockedIpAddress($decoratedMiddleware);
$middleware->blockAddress('192.168.1.1');
$middleware->blockAddress('192.168.1.0/24');
$middleware->allowAddress('192.168.1.0/24');
```

## Position in Middleware Stack

It is recommended that this is the outermost middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere that does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` middleware, but it can decorate any server middleware.
