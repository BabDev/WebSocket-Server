<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;

/**
 * The RPC message middleware interface defines a message middleware component for incoming RPC WAMP messages.
 */
interface RPCMessageMiddleware extends MessageMiddleware
{
    /**
     * Handles an RPC "CALL" WAMP message from the client.
     *
     * @param string $id The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     */
    public function onCall(WAMPConnection $connection, string $id, WAMPMessageRequest $request, array $params): void;
}
