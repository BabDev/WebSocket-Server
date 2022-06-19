<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\MessageHandler;

use BabDev\WebSocket\Server\MessageHandler;
use BabDev\WebSocket\Server\MessageMiddleware;
use BabDev\WebSocket\Server\WAMP\Exception\CannotInstantiateMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidRequest;
use BabDev\WebSocket\Server\WAMP\Exception\UnknownMessageHandler;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;

/**
 * The message handler resolver is responsible for resolving the {@see MessageHandler} (including
 * its {@see MessageMiddleware}) for a request.
 */
interface MessageHandlerResolver
{
    /**
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws UnknownMessageHandler           if the message handler does not exist
     */
    public function findMessageHandler(WAMPMessageRequest $request): MessageHandler|MessageMiddleware;
}
