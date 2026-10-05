# Parse HTTP Request Middleware

The `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is used to read the initial HTTP request to the server and transforms it into a [PSR-7](https://www.php-fig.org/psr/psr-7/) `Psr\Http\Message\RequestInterface` object which is stored on the connection's attribute store.

## Request Parsing

The middleware requires a `BabDev\WebSocket\Server\Http\RequestParser` to transform the raw HTTP request body into a `Psr\Http\Message\RequestInterface` object. Parsers support chunked messages as read by the middleware and should check the `http.buffer` key on the connection's attribute store to decide if the full body has been received.

By default, the middleware will use the `BabDev\WebSocket\Server\Http\GuzzleRequestParser` class which relies on the [`guzzlehttp/psr7` package](https://docs.guzzlephp.org/en/stable/psr7.html). You can use your own parser by implementing the interface and passing your class as the second parameter to the middleware's constructor.

### Maximum Request Size

The `BabDev\WebSocket\Server\Http\GuzzleRequestParser` limits the size of the HTTP request to 4096 bytes by default; a client sending a larger request receives a `413 Request Entity Too Large` response and the connection is closed. The limit can be changed with the parser's constructor:

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\GuzzleRequestParser;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;

$middleware = new ParseHttpRequest($decoratedMiddleware, new GuzzleRequestParser(maxRequestSize: 8192));
```

## Request Timeout

To protect the server from clients which open a connection and never send a complete HTTP request, the middleware can close connections which do not send their request within a set time. Once the timeout expires, the client receives a `408 Request Timeout` response and the connection is closed. The timeout measures the total time to receive the request, so a client sending its request slowly is also closed.

To enable the request timeout, you can use the middleware's `enableRequestTimeout()` method, providing it the event loop and the timeout in seconds (defaults to 10):

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use React\EventLoop\Loop;

$middleware = new ParseHttpRequest($decoratedMiddleware);
$middleware->enableRequestTimeout(Loop::get(), 5.0);
```

The timeout applies to connections opened after it is enabled. When using the `BabDev\WebSocket\Server\Application` class, the request timeout is enabled with a 10 second timeout by default, and can be changed or disabled with the `withRequestTimeout()` method.

## Position in Middleware Stack

As most middleware require the HTTP request to function, it is recommended that this is the outermost middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware).

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\Http\Middleware\ResolveForwardedClientAddress` middleware when the server is behind a reverse proxy, or the `BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress` middleware otherwise, but it can decorate any server middleware.
