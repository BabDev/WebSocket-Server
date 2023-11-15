<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\ServerMiddleware;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * The initialize session server middleware reads the session data for the current connection into a
 * read-only {@see SessionInterface} instance.
 *
 * This middleware uses Symfony's HttpFoundation component to interact with the session data. Applications
 * using another session service will need their own middleware component.
 */
final readonly class InitializeSession implements ServerMiddleware
{
    public function __construct(
        private ServerMiddleware $middleware,
        private SessionFactoryInterface $sessionFactory,
        private OptionsHandler $optionsHandler = new IniOptionsHandler(),
    ) {}

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

        $session = $this->sessionFactory->createSession();

        if ($request->hasHeader('Cookie')) {
            $sessionName = $session->getName();

            foreach ($request->getHeader('Cookie') as $cookieHeader) {
                $cookies = $this->parseCookieHeader($cookieHeader);

                if (isset($cookies[$sessionName])) {
                    $session->setId($cookies[$sessionName]);

                    break;
                }
            }
        }

        if ($this->optionsHandler->get('session.auto_start')) {
            $session->start();
        }

        $connection->getAttributeStore()->set('session', $session);

        $this->middleware->onOpen($connection);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        $this->middleware->onMessage($connection, $data);
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        $this->middleware->onClose($connection);
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        $this->middleware->onError($connection, $throwable);
    }

    /**
     * Parses a `Cookie:` header value.
     *
     * Based on the cookie handling from `SAPI_TREAT_DATA_FUNC` in `main/php_variables.c` from the PHP source.
     */
    private function parseCookieHeader(string $cookieHeader): array
    {
        $cookies = [];

        $cookie = strtok($cookieHeader, ";\0");

        while ($cookie) {
            if (!str_contains($cookie, '=')) {
                throw new \RuntimeException('Invalid Cookie header.');
            }

            /** @var int $separatorPosition */
            $separatorPosition = strpos($cookie, '=');

            $key = ltrim(substr($cookie, 0, $separatorPosition));

            $cookies[$key] = rawurldecode(substr($cookie, $separatorPosition + 1));

            $cookie = strtok(";\0");
        }

        return $cookies;
    }
}
