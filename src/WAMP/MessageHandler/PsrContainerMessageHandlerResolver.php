<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\MessageHandler;

use BabDev\WebSocket\Server\MessageHandler;
use BabDev\WebSocket\Server\MessageMiddleware;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidRequest;
use BabDev\WebSocket\Server\WAMP\Exception\UnknownMessageHandler;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use Psr\Container\ContainerInterface;

/**
 * The PSR container message handler resolver is a message handler resolver which returns a message handler set on the route's
 * "_controller" attribute or attempts to locate the message handler from a PSR-11 compatible container.
 */
final class PsrContainerMessageHandlerResolver implements MessageHandlerResolver
{
    public function __construct(private readonly ContainerInterface $container) {}

    /**
     * @throws InvalidMessageHandler if the resolved object is not a valid message handler
     * @throws InvalidRequest        if the request data does not allow a handler to be resolved
     * @throws UnknownMessageHandler if the message handler does not exist in the container
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

        if (!$this->container->has($handler)) {
            throw new UnknownMessageHandler(sprintf('A message handler for service ID "%s" does not exist in the container.', $handler));
        }

        $handler = $this->container->get($handler);

        if (!$handler instanceof MessageHandler && !$handler instanceof MessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('A message handler resolver can only return instances of "%s" or "%s", "%s" returned.', MessageHandler::class, MessageMiddleware::class, get_debug_type($handler)));
        }

        return $handler;
    }
}
