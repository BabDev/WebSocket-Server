<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

/**
 * The keys reserved in a connection's {@see AttributeStore} by this package.
 */
final class AttributeKey
{
    public const string HTTP_BUFFER = 'http.buffer';
    public const string HTTP_HEADERS_RECEIVED = 'http.headers_received';
    public const string HTTP_REQUEST = 'http.request';
    public const string PROXY_ADDRESS = 'proxy_address';
    public const string REMOTE_ADDRESS = 'remote_address';
    public const string RESOURCE_ID = 'resource_id';
    public const string SESSION = 'session';
    public const string WAMP_PREFIXES = 'wamp.prefixes';
    public const string WAMP_SESSION_ID = 'wamp.session_id';
    public const string WAMP_SUBSCRIPTIONS = 'wamp.subscriptions';
    public const string WEBSOCKET_CLOSING = 'websocket.closing';

    private function __construct() {}
}
