<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Http\Exception\InvalidRequestTimeout;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\Http\Middleware\ResolveForwardedClientAddress;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\DefaultErrorUriResolver;
use BabDev\WebSocket\Server\WAMP\ErrorUriResolver;
use BabDev\WebSocket\Server\WAMP\MessageHandler\DefaultMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Log\LoggerInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

/**
 * This is an opinionated application class for building and running the websocket server.
 *
 * This will build the middleware stack for the server using the default implementations of all components provided
 * by this package. You will need your own implementation of the logic in this class to replace the implementations
 * or add extra middleware.
 */
final class Application
{
    private readonly LoopInterface $loop;

    private readonly UrlMatcherInterface $matcher;

    private RouteCollection $routeCollection;

    private MessageHandlerResolver $messageHandlerResolver;

    private ErrorUriResolver $errorUriResolver;

    private ?EventDispatcherInterface $dispatcher = null;

    private ?LoggerInterface $logger = null;

    /**
     * @var int<1, max>|null
     */
    private ?int $writeBufferLimit = ReactPhpServer::DEFAULT_WRITE_BUFFER_LIMIT;

    private ?float $shutdownTimeout = 5.0;

    private ?SessionFactoryInterface $sessionFactory = null;

    private ?OptionsHandler $optionsHandler = null;

    private ?float $requestTimeout = 10.0;

    /**
     * @var positive-int|null
     */
    private ?int $keepAliveInterval = null;

    /**
     * @var list<non-empty-string>
     */
    private array $allowedOrigins = [];

    /**
     * @var list<non-empty-string>
     */
    private array $blockedAddresses = [];

    /**
     * @var list<string>
     */
    private array $trustedProxies = [];

    /**
     * @var int-mask-of<Request::HEADER_*>
     */
    private int $trustedHeaderSet = Request::HEADER_X_FORWARDED_FOR;

    /**
     * Creates the application instance.
     *
     * This class' constructor arguments are forwarded to the underlying {@see SocketServer} instance which handles
     * the connections for the server. Please see that class' documentation for more details.
     */
    public function __construct(
        private readonly string $uri,
        private readonly array $context = [],
        ?LoopInterface $loop = null,
    ) {
        $this->loop = $loop ?? Loop::get();
        $this->matcher = new UrlMatcher(
            $this->routeCollection = new RouteCollection(),
            new RequestContext(),
        );
        $this->errorUriResolver = new DefaultErrorUriResolver();
        $this->messageHandlerResolver = new DefaultMessageHandlerResolver();
    }

    public function run(): void
    {
        $topicRegistry = new ArrayTopicRegistry();

        $middleware = new DispatchMessageToHandler($this->matcher, $this->messageHandlerResolver, $this->dispatcher, $this->errorUriResolver);
        $middleware = new UpdateTopicSubscriptions($middleware, $topicRegistry);
        $middleware = new ParseWAMPMessage($middleware, $topicRegistry);

        $middleware = $webSocketMiddleware = new EstablishWebSocketConnection($middleware);

        if (null !== $this->keepAliveInterval) {
            $middleware->enableKeepAlive($this->loop, $this->keepAliveInterval);
        }

        if ($this->sessionFactory instanceof SessionFactoryInterface) {
            $middleware = new InitializeSession($middleware, $this->sessionFactory, $this->optionsHandler ?? new IniOptionsHandler());
        }

        if ([] !== $this->allowedOrigins) {
            $middleware = new RestrictToAllowedOrigins($middleware, $this->allowedOrigins);
        }

        // The blocked address check runs once the client address is known, which behind a trusted proxy is only after the
        // HTTP request has been parsed
        if ([] !== $this->blockedAddresses) {
            $middleware = new RejectBlockedIpAddress($middleware, $this->blockedAddresses);
        }

        if ([] !== $this->trustedProxies) {
            $middleware = new ResolveForwardedClientAddress($middleware, $this->trustedProxies, $this->trustedHeaderSet);
        }

        $middleware = new ParseHttpRequest($middleware);

        if (null !== $this->requestTimeout) {
            $middleware->enableRequestTimeout($this->loop, $this->requestTimeout);
        }

        $socket = new SocketServer($this->uri, $this->context, $this->loop);

        $server = new ReactPhpServer($middleware, $socket, $this->loop, $this->logger, $this->writeBufferLimit);

        if (null !== $this->shutdownTimeout) {
            $this->registerShutdownSignals($webSocketMiddleware, $server, $this->shutdownTimeout);
        }

        $server->run();
    }

    public function route(string $path, MessageHandler|MessageMiddleware|string $handler, int $priority = 0): self
    {
        $this->routeCollection->add(
            'handler-'.$this->routeCollection->count(),
            new Route($path, ['_controller' => $handler]),
            $priority
        );

        return $this;
    }

    /**
     * Replaces the {@see ErrorUriResolver} implementation to be used by the application.
     */
    public function withErrorUriResolver(ErrorUriResolver $errorUriResolver): self
    {
        $this->errorUriResolver = $errorUriResolver;

        return $this;
    }

    /**
     * Registers an event dispatcher for use with middleware that emit events.
     */
    public function withEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->dispatcher = $eventDispatcher;

        return $this;
    }

    /**
     * Enables the keepalive ping-pong, closing connections which do not respond to a ping before the next one is sent.
     *
     * @param positive-int $interval The number of seconds between pings
     */
    public function withKeepAlive(int $interval = 30): self
    {
        $this->keepAliveInterval = $interval;

        return $this;
    }

    /**
     * Sets the number of seconds a client has to send its HTTP request before the connection is closed.
     *
     * @throws InvalidRequestTimeout if the timeout is not a positive number
     */
    public function withRequestTimeout(?float $timeout): self
    {
        if (null !== $timeout && $timeout <= 0) {
            throw new InvalidRequestTimeout($timeout, \sprintf('The request timeout must be a positive number, %s given.', $timeout));
        }

        $this->requestTimeout = $timeout;

        return $this;
    }

    /**
     * Sets the reverse proxies which are trusted to report the address of the client.
     *
     * When the server is behind a trusted proxy, the {@see ResolveForwardedClientAddress} middleware is registered to
     * read the client address from the forwarding headers, so blocked addresses are checked against the client address
     * instead of the proxy's address. This follows the same conventions as Symfony's {@see Request::setTrustedProxies()}.
     *
     * @param list<string>                   $proxies          The addresses or subnets of the trusted proxies; the string "REMOTE_ADDR" trusts the
     *                                                         address of every connection, and "PRIVATE_SUBNETS" trusts all private network ranges
     * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet A bit field of the headers to trust from the proxies; only the
     *                                                         {@see Request::HEADER_FORWARDED} and {@see Request::HEADER_X_FORWARDED_FOR} headers are used
     */
    public function withTrustedProxies(array $proxies, int $trustedHeaderSet = Request::HEADER_X_FORWARDED_FOR): self
    {
        $this->trustedProxies = $proxies;
        $this->trustedHeaderSet = $trustedHeaderSet;

        return $this;
    }

    /**
     * @param int<1, max>|null $limit
     */
    public function withWriteBufferLimit(?int $limit): self
    {
        $this->writeBufferLimit = $limit;

        return $this;
    }

    /**
     * Sets the number of seconds to wait for connections to close when the server is stopped by a SIGTERM or SIGINT
     * signal, or null to not handle these signals.
     */
    public function withShutdownTimeout(?float $timeout): self
    {
        $this->shutdownTimeout = $timeout;

        return $this;
    }

    /**
     * Registers a logger for reporting failures the server middleware stack could not handle.
     */
    public function withLogger(LoggerInterface $logger): self
    {
        $this->logger = $logger;

        return $this;
    }

    /**
     * Replaces the {@see MessageHandlerResolver} implementation to be used by the application.
     */
    public function withMessageHandlerResolver(MessageHandlerResolver $messageHandlerResolver): self
    {
        $this->messageHandlerResolver = $messageHandlerResolver;

        return $this;
    }

    /**
     * Enables the {@see InitializeSession} server middleware.
     */
    public function withSession(SessionFactoryInterface $sessionFactory, ?OptionsHandler $optionsHandler = null): self
    {
        $this->sessionFactory = $sessionFactory;
        $this->optionsHandler = $optionsHandler;

        return $this;
    }

    /**
     * Allows a previously blocked IP address to access the server.
     *
     * The {@see RejectBlockedIpAddress} middleware will automatically be registered if addresses have been blocked
     * before running the server.
     *
     * @param non-empty-string $address
     */
    public function allowAddress(string $address): self
    {
        $this->blockedAddresses = array_values(
            array_filter(
                $this->blockedAddresses,
                /** @var non-empty-string $blockedAddress */
                static fn (string $blockedAddress): bool => $blockedAddress !== $address,
            ),
        );

        return $this;
    }

    /**
     * Blocks an IP address from accessing the server.
     *
     * The {@see RejectBlockedIpAddress} middleware will automatically be registered if addresses have been blocked
     * before running the server.
     *
     * @param non-empty-string $address
     */
    public function blockAddress(string $address): self
    {
        $this->blockedAddresses[] = $address;

        return $this;
    }

    /**
     * Allows an origin to access the server.
     *
     * The default middleware makes access decisions based on the `Origin` header of an incoming HTTP request.
     *
     * The {@see RestrictToAllowedOrigins} middleware will automatically be registered if the server is restricted
     * to a list of allowed origins before running the server.
     *
     * @param non-empty-string $origin
     */
    public function allowOrigin(string $origin): self
    {
        $this->allowedOrigins[] = $origin;

        return $this;
    }

    /**
     * Removes an origin from the list allowed to access the server.
     *
     * The default middleware makes access decisions based on the `Origin` header of an incoming HTTP request.
     *
     * The {@see RestrictToAllowedOrigins} middleware will automatically be registered if the server is restricted
     * to a list of allowed origins before running the server.
     *
     * @param non-empty-string $origin
     */
    public function removeAllowedOrigin(string $origin): self
    {
        $this->allowedOrigins = array_values(
            array_filter(
                $this->allowedOrigins,
                /** @var non-empty-string $allowedOrigin */
                static fn (string $allowedOrigin): bool => $allowedOrigin !== $origin,
            ),
        );

        return $this;
    }

    private function registerShutdownSignals(EstablishWebSocketConnection $webSocketMiddleware, ReactPhpServer $server, float $timeout): void
    {
        if (!\defined('SIGTERM') || !\defined('SIGINT')) {
            return;
        }

        $signals = [\SIGTERM, \SIGINT];

        $handler = function () use (&$handler, $signals, $webSocketMiddleware, $server, $timeout): void {
            // Restore the default signal handling so a second signal stops the server immediately
            foreach ($signals as $signal) {
                $this->loop->removeSignal($signal, $handler);
            }

            $webSocketMiddleware->closeAllConnections();
            $server->shutdown($timeout);
        };

        try {
            foreach ($signals as $signal) {
                $this->loop->addSignal($signal, $handler);
            }
        } catch (\BadMethodCallException) {
            // The event loop does not support signals
        }
    }
}
