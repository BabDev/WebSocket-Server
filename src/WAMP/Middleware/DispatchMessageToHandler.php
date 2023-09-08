<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\Event\ConnectionClosed;
use BabDev\WebSocket\Server\Connection\Event\ConnectionError;
use BabDev\WebSocket\Server\Connection\Event\ConnectionOpened;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\RPCMessageHandler;
use BabDev\WebSocket\Server\RPCMessageMiddleware;
use BabDev\WebSocket\Server\TopicMessageHandler;
use BabDev\WebSocket\Server\TopicMessageMiddleware;
use BabDev\WebSocket\Server\WAMP\Exception\CannotInstantiateMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidRequest;
use BabDev\WebSocket\Server\WAMP\Exception\RouteNotFound;
use BabDev\WebSocket\Server\WAMP\Exception\UnknownMessageHandler;
use BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use BabDev\WebSocket\Server\WAMPServerMiddleware;
use BabDev\WebSocket\Server\WebSocketException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * The dispatch message to server middleware routes an incoming WAMP message using the topic identifier to its
 * message handler.
 *
 * This middleware supports emitting events when provided a PSR-14 compatible event dispatcher, allowing listeners
 * to take actions after a client connection has been opened, closed, or when the server middleware catches an unhandled
 * {@see \Throwable}. As this middleware is intended to be the last middleware executed in the server middleware stack,
 * these events are provided for downstream consumers to react after the server middleware stack has handled an action,
 * however it is preferred that consumers implement their own middleware (especially those who wish to monitor one of
 * these events at a higher point in the stack).
 *
 * This middleware uses Symfony's Routing component to create a router for the server application. Applications
 * choosing to use another router service will need their own middleware component.
 */
final class DispatchMessageToHandler implements WAMPServerMiddleware
{
    public function __construct(
        private readonly UrlMatcherInterface $matcher,
        private readonly MessageHandlerResolver $resolver,
        private readonly ?EventDispatcherInterface $dispatcher = null,
    ) {}

    /**
     * @return list<string>
     */
    public function getSubProtocols(): array
    {
        return [];
    }

    /**
     * Handles a new connection to the server.
     *
     * @throws MissingRequest if the HTTP request has not been parsed before this middleware is executed
     */
    public function onOpen(Connection $connection): void
    {
        $this->dispatcher?->dispatch(new ConnectionOpened($connection));
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        // No decorated middleware to call
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        $this->dispatcher?->dispatch(new ConnectionClosed($connection));
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->dispatcher?->dispatch(new ConnectionError($connection, $throwable));
    }

    /**
     * Handles an RPC "CALL" WAMP message from the client.
     *
     * @param string $id          The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     * @param string $resolvedUri The URI that identifies the remote procedure, after resolving any CURIE prefixed URIs
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     */
    public function onCall(WAMPConnection $connection, string $id, string $resolvedUri, array $params): void
    {
        try {
            $request = $this->route($resolvedUri);
        } catch (RouteNotFound $exception) {
            $connection->callError(
                $id,
                'https://example.com/error#not-found', // TODO - Make the error URI customizable
                sprintf('Could not find a message handler for URI "%s".', $resolvedUri),
                [
                    'code' => 404,
                    'uri' => $resolvedUri,
                ]
            );

            throw $exception;
        }

        try {
            $handler = $this->resolver->findMessageHandler($request);
        } catch (WebSocketException $exception) {
            $connection->callError(
                $id,
                'https://example.com/error#not-found', // TODO - Make the error URI customizable
                sprintf('Could not find a message handler for URI "%s".', $resolvedUri),
                [
                    'code' => 404,
                    'uri' => $resolvedUri,
                ]
            );

            throw $exception;
        }

        if (!$handler instanceof RPCMessageHandler && !$handler instanceof RPCMessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('The message handler for a "CALL" message must be an instance of "%s" or "%s", ensure "%s" implements the right interface.', RPCMessageHandler::class, RPCMessageMiddleware::class, $handler::class));
        }

        $handler->onCall($connection, $id, $request, $params);
    }

    /**
     * Handles a "SUBSCRIBE" WAMP message from the client.
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     */
    public function onSubscribe(WAMPConnection $connection, Topic $topic): void
    {
        try {
            $request = $this->route($topic->id);
        } catch (RouteNotFound $exception) {
            $connection->event(
                $topic->id,
                [
                    'error' => true,
                    'message' => sprintf('Could not find a message handler for URI "%s".', $topic->id),
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        try {
            $handler = $this->resolver->findMessageHandler($request);
        } catch (WebSocketException $exception) {
            $connection->event(
                $topic->id,
                [
                    'error' => true,
                    'message' => sprintf('Could not find a message handler for URI "%s".', $topic->id),
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        if (!$handler instanceof TopicMessageHandler && !$handler instanceof TopicMessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('The message handler for a "SUBSCRIBE" message must be an instance of "%s" or "%s", ensure "%s" implements the right interface.', TopicMessageHandler::class, TopicMessageMiddleware::class, $handler::class));
        }

        $handler->onSubscribe($connection, $topic, $request);
    }

    /**
     * Handles an "UNSUBSCRIBE" WAMP message from the client.
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     */
    public function onUnsubscribe(WAMPConnection $connection, Topic $topic): void
    {
        try {
            $request = $this->route($topic->id);
        } catch (RouteNotFound $exception) {
            $connection->event(
                $topic->id,
                [
                    'error' => true,
                    'message' => sprintf('Could not find a message handler for URI "%s".', $topic->id),
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        try {
            $handler = $this->resolver->findMessageHandler($request);
        } catch (WebSocketException $exception) {
            $connection->event(
                $topic->id,
                [
                    'error' => true,
                    'message' => sprintf('Could not find a message handler for URI "%s".', $topic->id),
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        if (!$handler instanceof TopicMessageHandler && !$handler instanceof TopicMessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('The message handler for a "UNSUBSCRIBE" message must be an instance of "%s" or "%s", ensure "%s" implements the right interface.', TopicMessageHandler::class, TopicMessageMiddleware::class, $handler::class));
        }

        $handler->onUnsubscribe($connection, $topic, $request);
    }

    /**
     * Handles a "PUBLISH" WAMP message from the client.
     *
     * @param array|string $event    The event payload for the message
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     */
    public function onPublish(WAMPConnection $connection, Topic $topic, array|string $event, array $exclude, array $eligible): void
    {
        try {
            $request = $this->route($topic->id);
        } catch (RouteNotFound $exception) {
            $connection->event(
                $topic->id,
                [
                    'error' => true,
                    'message' => sprintf('Could not find a message handler for URI "%s".', $topic->id),
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        try {
            $handler = $this->resolver->findMessageHandler($request);
        } catch (WebSocketException $exception) {
            $connection->event(
                $topic->id,
                [
                    'error' => true,
                    'message' => sprintf('Could not find a message handler for URI "%s".', $topic->id),
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        if (!$handler instanceof TopicMessageHandler && !$handler instanceof TopicMessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('The message handler for a "PUBLISH" message must be an instance of "%s" or "%s", ensure "%s" implements the right interface.', TopicMessageHandler::class, TopicMessageMiddleware::class, $handler::class));
        }

        $handler->onPublish($connection, $topic, $request, $event, $exclude, $eligible);
    }

    /**
     * @throws RouteNotFound if there is no route defined for the topic ID
     */
    private function route(string $uri): WAMPMessageRequest
    {
        try {
            $parameters = $this->matcher->match($uri);
        } catch (ResourceNotFoundException $exception) {
            throw new RouteNotFound(sprintf('Could not find a message handler for URI "%s".', $uri), $exception->getCode(), $exception);
        }

        // This snippet emulates the HttpKernel's RouterListener behavior for setting route attributes to the request
        $attributes = new ParameterBag($parameters);
        unset($parameters['_route'], $parameters['_controller']);
        $attributes->set('_route_params', $parameters);

        return new WAMPMessageRequest($attributes);
    }
}
