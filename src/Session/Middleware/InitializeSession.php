<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Http\Exception\InvalidRequestHeader;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Session\Exception\InvalidSession;
use BabDev\WebSocket\Server\Session\Exception\SessionMisconfigured;
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
    use ClosesConnectionWithResponse;

    public function __construct(
        private ServerMiddleware $middleware,
        private SessionFactoryInterface $sessionFactory,
        private OptionsHandler $optionsHandler = new IniOptionsHandler(),
    ) {}

    /**
     * Handles a new connection to the server.
     *
     * @throws InvalidRequestHeader if the Cookie header contains an invalid value
     * @throws InvalidSession       if the session data could not be read when auto-start is enabled
     * @throws MissingRequest       if the HTTP request has not been parsed before this middleware is executed
     * @throws SessionMisconfigured if the session configuration is invalid when auto-start is enabled
     */
    public function onOpen(Connection $connection): void
    {
        /** @var RequestInterface|null $request */
        $request = $connection->getAttributeStore()->get('http.request');

        if (!$request instanceof RequestInterface) {
            throw new MissingRequest(\sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        $session = $this->sessionFactory->createSession();

        if ($request->hasHeader('Cookie')) {
            $sessionName = $session->getName();

            foreach ($request->getHeader('Cookie') as $cookieHeader) {
                try {
                    $cookies = $this->parseCookieHeader($cookieHeader);

                    if (isset($cookies[$sessionName])) {
                        $session->setId($cookies[$sessionName]);

                        break;
                    }
                } catch (InvalidRequestHeader $exception) {
                    // We'll have a bad request at this point, let's go ahead and close the connection before letting the stack handle the error
                    $this->close($connection, 400);

                    throw $exception;
                }
            }
        }

        if ($this->optionsHandler->get('session.auto_start')) {
            try {
                $session->start();
            } catch (InvalidSession|SessionMisconfigured $exception) {
                // Something went wrong trying to use the session data, bail out
                $this->close($connection, 500);

                throw $exception;
            }
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
     *
     * @throws InvalidRequestHeader if the Cookie header contains an invalid value
     */
    private function parseCookieHeader(string $cookieHeader): array
    {
        $cookies = [];

        $cookie = strtok($cookieHeader, ";\0");

        while ($cookie) {
            if (!str_contains($cookie, '=')) {
                throw new InvalidRequestHeader('Cookie', $cookieHeader, 'Invalid Cookie header.');
            }

            /** @var int<0, max> $separatorPosition */
            $separatorPosition = strpos($cookie, '=');

            $key = ltrim(substr($cookie, 0, $separatorPosition));

            $cookies[$key] = rawurldecode(substr($cookie, $separatorPosition + 1));

            $cookie = strtok(";\0");
        }

        return $cookies;
    }
}
