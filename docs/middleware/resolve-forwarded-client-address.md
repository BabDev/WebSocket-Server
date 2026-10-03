# Resolve Forwarded Client Address Middleware

The `BabDev\WebSocket\Server\Http\Middleware\ResolveForwardedClientAddress` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is used when the websocket server is behind a reverse proxy (such as nginx or a load balancer).

Behind a proxy, every connection to the server comes from the proxy, so the connection's `remote_address` attribute is the proxy's IP address instead of the client's. When a connection comes from a trusted proxy, this middleware reads the client's IP address from the forwarding headers set by the proxy and replaces the `remote_address` attribute with it, keeping the proxy's IP address in the `proxy_address` attribute. Middleware which run after this middleware, such as the [`BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress`](/open-source/packages/websocket-server/docs/1.x/middleware/reject-blocked-ip-address) middleware, then use the client's IP address.

The middleware follows the same conventions as the trusted proxy support in [Symfony's `Request` class](https://symfony.com/doc/current/deployment/proxies.html), and uses the same constants to configure which headers are trusted.

## Configuring Trusted Proxies

The middleware requires the list of trusted proxies and, optionally, a bit field of the headers to trust (defaults to `X-Forwarded-For`):

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\ResolveForwardedClientAddress;
use Symfony\Component\HttpFoundation\Request;

$middleware = new ResolveForwardedClientAddress(
    $decoratedMiddleware,
    ['192.0.2.1', '10.0.0.0/8'],
    Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED,
);
```

The list of trusted proxies supports single IP addresses and subnet ranges, as well as two special values:

- `PRIVATE_SUBNETS` - trusts all private network ranges (see `Symfony\Component\HttpFoundation\IpUtils::PRIVATE_SUBNETS`)
- `REMOTE_ADDR` - trusts every connection; only use this when the server can only be reached through the proxy

Only the `Request::HEADER_X_FORWARDED_FOR` (the `X-Forwarded-For` header) and `Request::HEADER_FORWARDED` (the [RFC 7239](https://datatracker.ietf.org/doc/html/rfc7239) `Forwarded` header) values are used by this middleware, any other headers in the bit field are ignored.

Only trust the headers your proxy sets. A proxy which sets one of these headers usually passes the other through from the client unchanged, so trusting a header your proxy does not set allows clients to send any IP address.

## Resolving the Client Address

The forwarding headers contain the list of addresses the request passed through. The client address is the last address in the list which is not a trusted proxy, so a client cannot spoof its address by sending its own forwarding header. If every address is a trusted proxy, the first address is used. Invalid addresses are ignored, and the client address is normalized in the same way as the `remote_address` attribute (see the [connection documentation](/open-source/packages/websocket-server/docs/1.x/connection#remote-address-normalization)).

If both headers are trusted and they resolve to different client addresses, the connection is closed with a `400 Bad Request` response and a `BabDev\WebSocket\Server\Http\Exception\ConflictingForwardedHeaders` exception is thrown.

## Position in Middleware Stack

This middleware requires the HTTP request, so it must be decorated by the `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` middleware (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware).

It is recommended that this middleware decorates the `BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress` middleware, so blocked addresses are checked against the client's IP address.

When using the `BabDev\WebSocket\Server\Application` class, this middleware is registered with the `withTrustedProxies()` method.
