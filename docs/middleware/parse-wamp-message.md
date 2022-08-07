# Parse WAMP Message Middleware

The `BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which parses an incoming WAMP message to be consumed by a [message handler](/open-source/packages/websocket-server/docs/1.x/message-handler).

The middleware also allows enabling a keepalive ping-pong for the server.

## Customizing The Server Identity

Per the [WAMP version 1](https://web.archive.org/web/20150419051041/http://wamp.ws/spec/wamp1/) specification, a server may identify itself with the `serverIdent` parameter in its response to the WELCOME message. By default, the middleware uses the `BabDev\WebSocket\Server\Server::VERSION` constant as its identity, but this can be customized by updating the `$serverIdentity` property of the middleware instance.

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;

$middleware = new ParseWAMPMessage($decoratedMiddleware, $topicRegistry);
$middleware->serverIdentity = ''; // An empty string is allowed to not disclose any identity
$middleware->serverIdentity = 'My-Awesome-Application/1.0';
```

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere after the HTTP request has been parsed and does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions` middleware, but it can decorate any WAMP server middleware.
