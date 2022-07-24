# Middleware

Similar to [Ratchet](https://github.com/ratchetphp/Ratchet), the WebSocket Server package uses a middleware based architecture to represent the application.

One of the biggest differences with this package in comparison to Ratchet is that each middleware is isolated with full dependency injection (along with sane defaults where practical), which makes it simpler to customize the application if desired.

Middleware are defined by the `BabDev\WebSocket\Server\Middleware` interface and generally fall into two groups:

- Server middleware
- Message middleware

## Server Middleware

Server middleware is triggered for every incoming message to the server, when a client connection to the server has been closed, or on an error from the client connection or an unhandled exception on the server. Responsibilities include:

- Parsing the incoming HTTP request
- Parsing the incoming WAMP message
- Dispatching the message to the appropriate [message handler](/open-source/packages/websocket-server/docs/1.x/message-handler) (which may be decorated by its own middleware)

The server middleware is further separated into three groups:

- General middleware, represented by `BabDev\WebSocket\Server\ServerMiddleware` implementations
- WebSocket middleware, represented by `BabDev\WebSocket\Server\WebSocketServerMiddleware` implementations
- WAMP middleware, represented by `BabDev\WebSocket\Server\WAMPServerMiddleware` implementations

The incoming message workflow starts with the general middleware. The main purpose of this group of middleware is to handle the initial incoming HTTP request and forward the message down the stack. This is also a good level in the middleware stack to add extra features for your application, examples of these types of features include:

- Rejecting incoming connections based on IP address
- Reading the session data from the web frontend of your application
- Authenticating users

The message handling then moves to the WebSocket middleware group. This group is responsible for validating the incoming HTTP request and sending the appropriate HTTP response to the client which will either establish the WebSocket connection to the server or close the request.

Once the WebSocket connection is established, the message handling then moves into the WAMP middleware group. This group is responsible for validating and parsing the incoming WAMP message then dispatching the message to the appropriate message handler.

## Message Middleware

This middleware decorates a message handler and allows applications to take actions on a per-message basis.

Message middleware is separated into two groups:

- RPC message middleware, represented by `BabDev\WebSocket\Server\RPCMessageMiddleware` implementations
- Topic (PubSub) message middleware, represented by `BabDev\WebSocket\Server\TopicMessageMiddleware` implementations

This means a message middleware can support both RPC and Topic handlers or only one message group specifically. An example of a message middleware implementation could include access control checks before handling a message.
