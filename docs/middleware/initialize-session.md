# Initialize Session Middleware

The `BabDev\WebSocket\Server\Session\Middleware\InitializeSession` class is a [server middleware](/open-source/packages/websocket-server/docs/1.x/middleware) which reads the session data from the web-facing frontend of your application.

This middleware requires the session classes from Symfony's HttpFoundation component and will store the initialized session to the connection's attribute store.

Please see the [Symfony documentation](https://symfony.com/doc/current/session.html) for more information on how to use their API.

## Creating the Middleware

The middleware requires two arguments; the decorated middleware and a session factory.

```php
<?php declare(strict_types=1);

use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\Session\SessionFactory;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorageFactory;

$middleware = new InitializeSession(
    $decoratedMiddleware,
    new SessionFactory(new ReadOnlyNativeSessionStorageFactory()),
);
```

## Accessing the Session

To access the session, you can retrieve it using the "session" key from the connection's attribute store.

```php
$session = $connection->getAttributeStore()->get('session');
```

## Position in Middleware Stack

It is recommended that this middleware is decorated by the `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` middleware in your application (see the [message flow](/open-source/packages/websocket-server/docs/1.x/architecture#message-flow) section from the architecture documentation to see the recommended stack with all optional middleware), however it can be placed anywhere after the HTTP request has been parsed and does not expect one of the server middleware sub-interfaces.

It is also recommended that this middleware decorates the `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` middleware, but it can decorate any server middleware.
