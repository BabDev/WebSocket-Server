<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server;

use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\Middleware\RejectBlockedIpAddress;
use BabDev\WebSocket\Server\Http\Middleware\RestrictToAllowedOrigins;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use BabDev\WebSocket\Server\WAMP\ArrayTopicRegistry;
use BabDev\WebSocket\Server\WAMP\MessageHandler\DefaultMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Middleware\ParseWAMPMessage;
use BabDev\WebSocket\Server\WAMP\Middleware\UpdateTopicSubscriptions;
use BabDev\WebSocket\Server\WebSocket\Middleware\EstablishWebSocketConnection;
use Psr\EventDispatcher\EventDispatcherInterface;
use React\EventLoop\Loop;
use React\EventLoop\LoopInterface;
use React\Socket\SocketServer;
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

    private ?EventDispatcherInterface $dispatcher = null;

    private ?SessionFactoryInterface $sessionFactory = null;

    private ?OptionsHandler $optionsHandler = null;

    /**
     * @var list<non-empty-string>
     */
    private array $allowedOrigins = [];

    /**
     * @var list<non-empty-string>
     */
    private array $blockedAddresses = [];

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
        $this->messageHandlerResolver = new DefaultMessageHandlerResolver();
    }

    public function run(): void
    {
        $topicRegistry = new ArrayTopicRegistry();

        $middleware = new DispatchMessageToHandler($this->matcher, $this->messageHandlerResolver, $this->dispatcher);
        $middleware = new UpdateTopicSubscriptions($middleware, $topicRegistry);
        $middleware = new ParseWAMPMessage($middleware, $topicRegistry);

        $middleware = new EstablishWebSocketConnection($middleware);

        if ($this->sessionFactory instanceof SessionFactoryInterface) {
            $middleware = new InitializeSession($middleware, $this->sessionFactory, $this->optionsHandler ?? new IniOptionsHandler());
        }

        if ([] !== $this->allowedOrigins) {
            $middleware = new RestrictToAllowedOrigins($middleware, $this->allowedOrigins);
        }

        $middleware = new ParseHttpRequest($middleware);

        if ([] !== $this->blockedAddresses) {
            $middleware = new RejectBlockedIpAddress($middleware, $this->blockedAddresses);
        }

        $socket = new SocketServer($this->uri, $this->context, $this->loop);

        (new ReactPhpServer($middleware, $socket, $this->loop))->run();
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
     * Registers an event dispatcher for use with middleware that emit events.
     */
    public function withEventDispatcher(EventDispatcherInterface $eventDispatcher): self
    {
        $this->dispatcher = $eventDispatcher;

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
        $this->blockedAddresses = array_filter($this->blockedAddresses, static fn (string $blockedAddress): bool => $blockedAddress !== $address);

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
        $this->allowedOrigins = array_filter($this->allowedOrigins, static fn (string $allowedOrigin): bool => $allowedOrigin !== $origin);

        return $this;
    }
}
