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
 * The default message handler resolver is a message handler resolver which returns a message handler set on the route's
 * "_controller" attribute or attempts to create a new instance of a handler class.
 */
final class DefaultMessageHandlerResolver implements MessageHandlerResolver
{
    /**
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws UnknownMessageHandler           if the message handler does not exist
     */
    public function findMessageHandler(WAMPMessageRequest $request): MessageHandler|MessageMiddleware
    {
        if (!$handler = $request->attributes->get('_controller')) {
            throw new InvalidRequest(sprintf('Cannot resolve a message handler in "%s" when the "_controller" parameter is not set in the request attributes.', self::class));
        }

        if ($handler instanceof MessageHandler || $handler instanceof MessageMiddleware) {
            return $handler;
        }

        if (!\is_string($handler)) {
            throw new InvalidRequest(sprintf('The "%s" class only supports strings or an instance of "%s" or "%s" as the "_controller" parameter in the request attributes, "%s" given.', self::class, MessageHandler::class, MessageMiddleware::class, get_debug_type($handler)));
        }

        if (!class_exists($handler)) {
            throw new UnknownMessageHandler(sprintf('Message handler "%s" does not exist.', $handler));
        }

        try {
            $handler = new $handler();
        } catch (\ArgumentCountError $exception) {
            throw new CannotInstantiateMessageHandler($handler, sprintf('Cannot instantiate message handler "%s" in "%s", only handlers with no constructors can be instantiated by this resolver.', $handler, self::class), 0, $exception);
        }

        if (!$handler instanceof MessageHandler && !$handler instanceof MessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('A message handler resolver can only return instances of "%s" or "%s", ensure "%s" implements the right interface.', MessageHandler::class, MessageMiddleware::class, $handler::class));
        }

        return $handler;
    }
}
