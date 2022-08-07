# Update Topic Subscriptions Middleware

The `BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which updates the topic registry as connections are opened and closed.

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\WebSocket\Middleware\ParseWAMPMessage` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be decorated by any WAMP server middleware.

It is also recommended that this middleware decorates the `use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler` middleware, but it can decorate any WAMP server middleware.
