# Message Handler

Message handlers, defined by the `BabDev\WebSocket\Server\MessageHandler` interface, are responsible for handling an incoming WAMP message. For RPC message handlers, they are responsible for sending the corresponding "CALLRESULT" or "CALLERROR" message back to the client.

Message handlers are separated into two groups:

- RPC message handlers, represented by `BabDev\WebSocket\Server\RPCMessageHandler` implementations
- Topic (PubSub) message handlers, represented by `BabDev\WebSocket\Server\TopicMessageHandler` implementations

This means a message handler can support both RPC and Topic messages or a single message type.
