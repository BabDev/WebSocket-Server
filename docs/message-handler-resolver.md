# Message Handler Resolver

The `BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver` interface defines a service locator for message handlers. The resolver will provide the message handler, which may include any decorating message middleware for that object. Fundamentally, this resolver service is similar in scope/design to the controller resolvers found in Symfony's HttpKernel component.

This package provides two implementations which should be suitable for the overwhelming majority of use cases:

- The "basic" default resolver supports creating an instance of a message handler (without middleware or any required constructor arguments) or returning a handler set on the routing definition; an instance created by the resolver is reused for all later messages routed to the same handler class
- The [PSR-11](https://www.php-fig.org/psr/psr-11/) compatible resolver supports retrieving a message handler from any PSR-11 service container or returning a handler set on the routing definition
