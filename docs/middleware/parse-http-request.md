# Parse HTTP Request Middleware

The `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is used to read the initial HTTP request to the server and transforms it into a [PSR-7](https://www.php-fig.org/psr/psr-7/) `Psr\Http\Message\RequestInterface` object which is stored on the connection's attribute store.

## Request Parsing

The middleware requires a `BabDev\WebSocket\Server\Http\RequestParser` to transform the raw HTTP request body into a `Psr\Http\Message\RequestInterface` object. Parsers support chunked messages as read by the middleware and should check the `http.buffer` key on the connection's attribute store to decide if the full body has been received.

By default, the middleware will use the `BabDev\WebSocket\Server\Http\GuzzleRequestParser` class which relies on the [`guzzlehttp/psr7` package](https://docs.guzzlephp.org/en/stable/psr7.html). You can use your own parser by implementing the interface and passing your class as the second parameter to the middleware's constructor.

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware). As most middleware require the HTTP request to function, this should be one of the first middleware in your stack.

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` middleware, but it can decorate any server middleware.
