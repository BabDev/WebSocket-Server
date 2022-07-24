# Connection

Connections to the websocket server are represented by the `BabDev\WebSocket\Server\Connection` interface.

At their core, a connection has two primary responsibilities:

- Send messages from the server to a connected client
- Store arbitrary data for the connection

During the message lifecycle, the connection object will be decorated by `BabDev\WebSocket\Server\WebSocket\WebSocketConnection` and `BabDev\WebSocket\Server\WAMP\WAMPConnection` implementations which assist in handling all WAMP server-to-client messages and resolving URIs for incoming messages.

## Attribute Store

Each connection has a `BabDev\WebSocket\Server\Connection\AttributeStore` attached to it and allows storing arbitrary data for the connection. During the connection lifecycle, several pieces of data will be added to the store by the middleware provided by this package. The below keys are reserved for specific purposes and should not be replaced:

| Key                     | Data                                                                            | Description                                                                                                                |
|-------------------------|---------------------------------------------------------------------------------|----------------------------------------------------------------------------------------------------------------------------|
| `http.buffer`           | string                                                                          | Temporary storage for the incoming HTTP request body (this buffer is cleared once the full body is received)               |
| `http.headers_received` | boolean                                                                         | Internal flag tracking whether the HTTP headers have been received and parsed                                              |
| `http.request`          | [PSR-7](https://www.php-fig.org/psr/psr-7/) `Psr\Http\Message\RequestInterface` | The fully parsed HTTP request body                                                                                         |
| `remote_address`        | string                                                                          | The remote address for the connected client                                                                                |
| `resource_id`           | integer                                                                         | An identifier for the underlying connection resource                                                                       |
| `session`               | `Symfony\Component\HttpFoundation\Session\SessionInterface`                     | When using the session middleware, the session is initialized into a `SessionInterface` implementation                     |
| `wamp.session_id`       | string                                                                          | A unique, randomly generated, identifier for the connection                                                                |
| `wamp.subscriptions`    | `SplObjectStorage<BabDev\WebSocket\Server\WAMP\Topic, null>`                    | A list of topics (PubSub channels) that the connection is currently subscribed to                                          |
| `wamp.prefixes`         | `array<string, string>`                                                         | An associative array storing the list of prefixes for the connection, as configured by the "PREFIX" WAMP message           |
| `websocket.closing`     | boolean                                                                         | Internal flag tracking if the connection is being closed; once set to true, no further messages will be sent to the client |

## Client Communication

The connection object exposes two methods to communicate with a connected client: `send()` and `close()`

The `BabDev\WebSocket\Server\Connection::send()` method can send any data string to the connected client. Generally, this should be an [RFC6455](https://datatracker.ietf.org/doc/html/rfc6455) compliant message with a `[WAMP v1](https://web.archive.org/web/20150419051041/http://wamp.ws/spec/wamp1/)` compliant payload.

The `BabDev\WebSocket\Server\Connection::close()` method is used to close a client connection, optionally sending a final message. Generally, the close message should be an RFC6455 compliant close control frame.

## Extended Connections

### WebSocket Connection

The `BabDev\WebSocket\Server\WebSocket\WebSocketConnection` interface extends the core connection interface to add extended messaging support.

Specifically, this interface extends the connection's `send()` method to support sending either any data string or a `Ratchet\RFC6455\Messaging\DataInterface` object from the [`ratchet/rfc6455` package](https://github.com/ratchetphp/RFC6455). This is beneficial for applications to have full control over the data frames sent to the client (i.e. to support sending binary data). Please review the package's documentation for additional usage details.

### WAMP Connection

The `BabDev\WebSocket\Server\WAMP\WAMPConnection` interface extends the WebSocket connection interface and adds methods to support WAMP server-to-client messages, as well as providing the URI resolver for CURIEs.

The `callResult()`, `callError()`, `event()`, and `prefix()` methods correspond to the WAMP server-to-client message types and are used to send RPC call results to the caller or broadcast events.

The `getUri()` method is used to resolve URIs for incoming messages and provides support for registered prefixes for the CURIE protocol.
