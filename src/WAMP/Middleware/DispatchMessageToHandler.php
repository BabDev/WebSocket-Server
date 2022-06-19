<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Exception\UnsupportedConnection;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
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
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\ParameterBag;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

/**
 * The dispatch message to server middleware routes an incoming WAMP message using the topic identifier to its
 * message handler.
 *
 * This middleware uses Symfony's Routing component to create a router for the server application. Applications
 * choosing to use another router service will need their own middleware component.
 */
final class DispatchMessageToHandler implements WAMPServerMiddleware
{
    public function __construct(
        private readonly UrlMatcherInterface $matcher,
        private readonly MessageHandlerResolver $resolver,
    ) {
    }

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
        /** @var RequestInterface|null $request */
        $request = $connection->getAttributeStore()->get('http.request');

        if (!$request instanceof RequestInterface) {
            throw new MissingRequest(sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        $this->matcher->getContext()
            ->setHost($request->getUri()->getHost());
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
        // No decorated middleware to call
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        // No decorated middleware to call
    }

    /**
     * Handles an RPC "CALL" WAMP message from the client.
     *
     * @param string $id The unique ID of the RPC, required to send a "CALLERROR" or "CALLRESULT" message
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     * @throws UnsupportedConnection           if the connection is not a {@see WAMPConnection} instance
     */
    public function onCall(Connection $connection, string $id, Topic $topic, array $params): void
    {
        try {
            $request = $this->route($topic);
        } catch (RouteNotFound $exception) {
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

            $connection->callError(
                $id,
                $topic->id,
                sprintf('Could not find a message handler for URI "%s".', $topic->id),
                [
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        try {
            $handler = $this->resolver->findMessageHandler($request);
        } catch (WebSocketException $exception) {
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

            $connection->callError(
                $id,
                $topic->id,
                sprintf('Could not find a message handler for URI "%s".', $topic->id),
                [
                    'code' => 404,
                    'uri' => $topic->id,
                ]
            );

            throw $exception;
        }

        if (!$handler instanceof RPCMessageHandler && !$handler instanceof RPCMessageMiddleware) {
            throw new InvalidMessageHandler(sprintf('The message handler for a "CALL" message must be an instance of "%s" or "%s", ensure "%s" implements the right interface.', RPCMessageHandler::class, RPCMessageMiddleware::class, $handler::class));
        }

        $handler->onCall($connection, $id, $topic, $request, $params);
    }

    /**
     * Handles a "SUBSCRIBE" WAMP message from the client.
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     * @throws UnsupportedConnection           if the connection is not a {@see WAMPConnection} instance
     */
    public function onSubscribe(Connection $connection, Topic $topic): void
    {
        try {
            $request = $this->route($topic);
        } catch (RouteNotFound $exception) {
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

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
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

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
     * @throws UnsupportedConnection           if the connection is not a {@see WAMPConnection} instance
     */
    public function onUnsubscribe(Connection $connection, Topic $topic): void
    {
        try {
            $request = $this->route($topic);
        } catch (RouteNotFound $exception) {
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

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
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

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
     * @param array        $exclude  A list of session IDs the message should be excluded from
     * @param array        $eligible A list of session IDs the message should be sent to
     *
     * @throws CannotInstantiateMessageHandler if the message handler cannot be instantiated by the resolver
     * @throws InvalidMessageHandler           if the resolved object is not a valid message handler
     * @throws InvalidRequest                  if the request data does not allow a handler to be resolved
     * @throws RouteNotFound                   if there is no route defined for the topic ID
     * @throws UnknownMessageHandler           if the message handler does not exist
     * @throws UnsupportedConnection           if the connection is not a {@see WAMPConnection} instance
     *
     * @phpstan-param list<string> $exclude
     * @phpstan-param list<string> $eligible
     */
    public function onPublish(Connection $connection, Topic $topic, array|string $event, array $exclude, array $eligible): void
    {
        try {
            $request = $this->route($topic);
        } catch (RouteNotFound $exception) {
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

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
            if (!$connection instanceof WAMPConnection) {
                throw new UnsupportedConnection(sprintf('The "%s" expects the connection to be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, $connection::class), $exception->getCode(), $exception);
            }

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
    private function route(Topic $topic): WAMPMessageRequest
    {
        try {
            $parameters = $this->matcher->match($topic->id);
        } catch (ResourceNotFoundException $exception) {
            throw new RouteNotFound(sprintf('Could not find a message handler for URI "%s".', $topic->id), $exception->getCode(), $exception);
        }

        // This snippet emulates the HttpKernel's RouterListener behavior for setting route attributes to the request
        $attributes = new ParameterBag($parameters);
        unset($parameters['_route'], $parameters['_controller']);
        $attributes->set('_route_params', $parameters);

        return new WAMPMessageRequest($attributes);
    }
}
