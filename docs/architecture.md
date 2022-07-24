# Architecture

## WAMP v1 Compliant

This package aims to be compliant to the [WAMP v1](https://web.archive.org/web/20150419051041/http://wamp.ws/spec/wamp1/) specification. The terminology used in this package does its best to align with the specification's features, to include support for:

- Remote Procedure Calls (RPC Message Handler)
- Publish & Subscribe (PubSub) message pattern (Topic Message Handler)
- URIs represented in the CURIE (Compact URI Expression) syntax

### Why WAMP v1 and Not v2?

The below is the opinion of the package author (Michael Babker).

To be honest, I've found the v2 specification difficult to follow and could not find a good reference implementation to study in a way that made sense to me. On the other hand, I've been working with Ratchet for several years, including maintaining an older Symfony bundle providing an integration of that package into Symfony.

While the WAMP v1 specification is absolutely deprecated, and finding resources supporting it is difficult (including the original v1 specification and the Autobahn|JS v1 implementations no longer being readily accessible), I've found the Ratchet architecture paired with ReactPHP to be more than suitable for my client work.

While some of the implementation details between this package and Ratchet differ, this package was very much designed as a modernized implementation of Ratchet to adapt to changes in modern PHP development and as an opportunity for me to re-evaluate the integrations I've been maintaining on my own for the last several years and build a fully comprehensive standalone library with framework integration.

## Server

At the root of the WebSocket Server package is the `BabDev\WebSocket\Server\Server` interface. The server is responsible for listening for incoming connections and dispatching messages to the middleware stack.

The default server implementation is based on the [ReactPHP Socket](https://reactphp.org/socket/) component.

## Middleware Based Design

Similar to [Ratchet](https://github.com/ratchetphp/Ratchet), the WebSocket Server package uses a [middleware based architecture](/open-source/packages/websocket-server/docs/1.x/middleware) to represent the application.

One of the biggest differences with this package in comparison to Ratchet is that each middleware is isolated with full dependency injection (along with sane defaults where practical), which makes it simpler to customize the application if desired.

## Message Handlers

A message handler is responsible for handling an incoming WAMP message. For RPC message handlers, they are responsible for sending the corresponding "CALLRESULT" or "CALLERROR" message back to the client.

### Message Handler Resolver

The [`BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver`](/open-source/packages/websocket-server/docs/1.x/message-handler-resolver) interface defines a service locator for message handlers. The resolver will provide the message handler, which may include any decorating message middleware for that object. Fundamentally, this resolver service is similar in scope/design to the controller resolvers found in Symfony's HttpKernel component.

## Connection

The [`BabDev\WebSocket\Server\Connection`](/open-source/packages/websocket-server/docs/1.x/connection) interface represents a client connection to the server. During the course of the message lifecycle, this connection is decorated and message middleware and handlers will receive a `BabDev\WebSocket\Server\WAMP\WAMPConnection` implementation which provides access to several shortcut methods to assist with handling all server-to-client WAMP messages, as well as providing the URI resolver for CURIEs.

Connections have an attribute store attached to them to allow storing arbitrary data for each connection (similar to the "attributes" `ParameterBag` on a Symfony `Request` object).

## Topic Registry

The WebSocket Server package uses a `BabDev\WebSocket\Server\WAMP\TopicRegistry` to centrally track all active topic (PubSub) channels and all active connections to those channels. This registry is highly beneficial in applications which need to broadcast messages from a message handler to all connected clients and can be viewed as the core data store for the active connections to the server.

## Message Flow

The below represents the message flow for an application using all middleware available in this package (optional middleware are noted as such):

- [`BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress`](/open-source/packages/websocket-server/docs/1.x/middleware/reject-blocked-ip-address) (optional) - Rejects incoming messages based on IP address, supports both single addresses and subnet ranges
- `BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest` - Parses the HTTP request used to establish the connection to the server and stores it as a [PSR-7](https://www.php-fig.org/psr/psr-7/) `Psr\Http\Message\RequestInterface` on the connection's attribute store
- `BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins` (optional) - Rejects incoming messages based on the `Origin` header from the HTTP request
- `BabDev\WebSocket\Server\Session\Middleware\InitializeSession` (optional) - Reads the data from the active session for the client connection from the main web frontend using the session classes from Symfony's HttpFoundation component and stores the `Session` object on the connection's attribute store
- `BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection` - Validates the incoming HTTP request and sends the HTTP response to the client, which will validate the WebSocket handshake and establish the connection or close the connection immediately; this middleware also supports a keepalive ping from the server to all connected clients
- `BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage` - Parses and validates an incoming WAMP message; for "PREFIX" messages the middleware will register a prefix to the connection's attribute store for use with CURIEs, and for all other client-to-server messages it will forward the message along the chain to the message handlers
- `BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions` - Ensures that the `BabDev\WebSocket\Server\WAMP\Topic` object representing a PubSub channel is stored in the registry (clearing it if all connections for that channel are closed) and updates the connection's attribute store with a list of active topic subscriptions
- `BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler` - Using Symfony's Routing component, this middleware will match the URI for the client-to-server message to a defined route for the application, locates the appropriate message handler using the message handler resolver, and forwards the message; as the last middleware in the server stack, this middleware can also optionally emit events using a [PSR-14](https://www.php-fig.org/psr/psr-14/) compatible event dispatcher when a connection is opened, closed, or an error occurs

## Application

The `BabDev\WebSocket\Server\Application` class is available to help with bootstrapping the server application. It is instantiated with the arguments for a `React\Socket\SocketServer` instance (please see the [ReactPHP Socket](https://reactphp.org/socket/) component documentation for details) and allows all optional features to be configured before running the server.
