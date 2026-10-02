# Parse WAMP Message Middleware

The `BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which parses an incoming WAMP message to be consumed by a [message handler](/open-source/packages/websocket-server/docs/1.x/message-handler).

The middleware also allows enabling a keepalive ping-pong for the server.

## Message Validation

Incoming messages are validated before they are forwarded to the decorated middleware, and a `BabDev\WebSocket\Server\WAMP\Exception\InvalidMessage` exception is thrown for a message which does not match the [WAMP version 1](https://web.archive.org/web/20150419051041/http://wamp.ws/spec/wamp1/) specification:

- The message must be a JSON array whose first element is an integer message type; an unknown message type throws a `BabDev\WebSocket\Server\WAMP\Exception\UnsupportedMessageType` exception
- The call ID of a "CALL" message, and the topic URI of a "SUBSCRIBE", "UNSUBSCRIBE", or "PUBLISH" message, must be a non-empty string; numeric values are accepted and converted to strings
- The procedure URI of a "CALL" message must be a non-empty string
- The event of a "PUBLISH" message is required and must be an array or a string
- The exclude parameter of a "PUBLISH" message must be a boolean or a list of session ID strings, and the eligible parameter must be a list of session ID strings

## Topic Lookup

For "SUBSCRIBE", "UNSUBSCRIBE", and "PUBLISH" messages, the middleware looks up the topic for the message's URI in the topic registry. If the topic is not registered, a new `BabDev\WebSocket\Server\WAMP\Topic` is created for the message, but it is not added to the registry; registering topics is the responsibility of the `BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions` middleware once a connection subscribes to the topic. This prevents clients from adding topics to the registry by sending messages for topics that no connection is subscribed to.

As a result, a message handler processing a "PUBLISH" message for a topic without any subscribers receives a topic which is not in the registry.

## Limiting Prefixes

Clients can register CURIE prefixes for their connection with the "PREFIX" message. To prevent a client from registering an unlimited number of prefixes, the middleware limits each connection to 100 prefixes by default; once the limit is reached, a `BabDev\WebSocket\Server\WAMP\Exception\PrefixLimitExceeded` exception is thrown for any new prefix, while already registered prefixes can still be replaced. The limit can be changed for the middleware instance.

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;

$middleware = new ParseWAMPMessage($decoratedMiddleware, $topicRegistry);
$middleware->setMaxPrefixes(25);
```

Both the prefix and its URI must be non-empty strings, otherwise a `BabDev\WebSocket\Server\WAMP\Exception\InvalidMessage` exception is thrown.

## Customizing The Server Identity

Per the [WAMP version 1](https://web.archive.org/web/20150419051041/http://wamp.ws/spec/wamp1/) specification, a server may identify itself with the `serverIdent` parameter in its response to the WELCOME message. By default, the middleware uses the `BabDev\WebSocket\Server\Server::VERSION` constant as its identity, but this can be customized by updating the server identity for the middleware instance.

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;

$middleware = new ParseWAMPMessage($decoratedMiddleware, $topicRegistry);
$middleware->setServerIdentity(''); // An empty string is allowed to not disclose any identity
$middleware->setServerIdentity('My-Awesome-Application/1.0');
```

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere after the HTTP request has been parsed and does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions` middleware, but it can decorate any WAMP server middleware.
