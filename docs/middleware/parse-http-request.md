# Parse HTTP Request Middleware

The `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which is used to read the initial HTTP request to the server and transforms it into a [PSR-7](https://www.php-fig.org/psr/psr-7/) `Psr\Http\Message\RequestInterface` object which is stored on the connection's attribute store.

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware). As most middleware require the HTTP request to function, this should be one of the first middleware in your stack.

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` middleware, but it can decorate any server middleware.
