<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * The restrict to allowed origins server middleware checks the Origin header from the HTTP request and blocks connections
 * that do not come from an allowed origin.
 */
final class RestrictToAllowedOrigins implements ServerMiddleware
{
    use ClosesConnectionWithResponse;

    /**
     * @var string[]
     */
    private array $allowedOrigins = [];

    public function __construct(
        private readonly ServerMiddleware $middleware,
    ) {
    }

    /**
     * Handles a new connection to the server.
     *
     * @throws MalformedRequest if the Origin header cannot be parsed
     * @throws MissingRequest   if the HTTP request has not been parsed before this middleware is executed
     */
    public function onOpen(Connection $connection): void
    {
        /** @var RequestInterface|null $request */
        $request = $connection->getAttributeStore()->get('http.request');

        if (!$request instanceof RequestInterface) {
            throw new MissingRequest(sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        if ($this->allowedOrigins !== []) {
            if (!$request->hasHeader('Origin')) {
                $this->close($connection, 403);

                return;
            }

            foreach ($request->getHeader('Origin') as $originHeader) {
                $parsedOriginHeader = parse_url($originHeader, PHP_URL_HOST);

                if (false === $parsedOriginHeader || null === $parsedOriginHeader) {
                    throw new MalformedRequest('The "Origin" header cannot be parsed.');
                }

                if (!in_array($parsedOriginHeader, $this->allowedOrigins, true)) {
                    $this->close($connection, 403);

                    return;
                }
            }
        }

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

    public function allowOrigin(string $origin): void
    {
        $this->allowedOrigins[] = $origin;
    }

    public function removeAllowedOrigin(string $origin): void
    {
        $this->allowedOrigins = array_filter($this->allowedOrigins, static fn (string $allowedOrigin): bool => $allowedOrigin !== $origin);
    }
}
