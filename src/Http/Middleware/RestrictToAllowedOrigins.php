<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Http\Exception\InvalidAllowedOrigin;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use Psr\Http\Message\RequestInterface;

/**
 * The restrict to allowed origins server middleware checks the Origin header from the HTTP request and blocks connections
 * that do not come from an allowed origin.
 *
 * An allowed origin can be given in one of two formats:
 *
 * - A full origin, such as "https://example.com" or "http://localhost:8080", which requires the scheme, host, and port
 *   of the Origin header to match; the default port for the "http", "https", "ws", and "wss" schemes may be omitted
 * - A host, such as "example.com", which allows the host from any scheme and port
 *
 * Hosts are compared case-insensitively.
 */
final class RestrictToAllowedOrigins implements ServerMiddleware
{
    use ClosesConnectionWithResponse;

    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ws' => 80,
        'wss' => 443,
    ];

    /**
     * @var list<non-empty-string>
     */
    private array $allowedOrigins = [];

    /**
     * @param list<non-empty-string> $allowedOrigins
     *
     * @throws InvalidAllowedOrigin if an allowed origin is not a valid origin or host
     */
    public function __construct(
        private readonly ServerMiddleware $middleware,
        array $allowedOrigins = [],
    ) {
        foreach ($allowedOrigins as $allowedOrigin) {
            $this->allowOrigin($allowedOrigin);
        }
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
        $request = $connection->getAttributeStore()->get(AttributeKey::HTTP_REQUEST);

        if (!$request instanceof RequestInterface) {
            throw new MissingRequest(\sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        if ([] !== $this->allowedOrigins) {
            if (!$request->hasHeader('Origin')) {
                $this->close($connection, 403);

                return;
            }

            foreach ($request->getHeader('Origin') as $originHeader) {
                $origin = $this->parseOrigin($originHeader);

                if (null === $origin) {
                    $this->close($connection, 400);

                    throw new MalformedRequest('The "Origin" header cannot be parsed.');
                }

                if (!$this->isAllowed($origin)) {
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

    /**
     * @param non-empty-string $origin
     *
     * @throws InvalidAllowedOrigin if the origin is not a valid origin or host
     */
    public function allowOrigin(string $origin): void
    {
        $this->allowedOrigins[] = $this->normalizeAllowedOrigin($origin);
    }

    /**
     * @param non-empty-string $origin
     *
     * @throws InvalidAllowedOrigin if the origin is not a valid origin or host
     */
    public function removeAllowedOrigin(string $origin): void
    {
        $origin = $this->normalizeAllowedOrigin($origin);

        $this->allowedOrigins = array_values(
            array_filter(
                $this->allowedOrigins,
                /** @var non-empty-string $allowedOrigin */
                static fn (string $allowedOrigin): bool => $allowedOrigin !== $origin,
            ),
        );
    }

    /**
     * @param array{origin: non-empty-string, host: non-empty-string} $origin
     */
    private function isAllowed(array $origin): bool
    {
        return \in_array($origin['origin'], $this->allowedOrigins, true) || \in_array($origin['host'], $this->allowedOrigins, true);
    }

    /**
     * @return non-empty-string
     *
     * @throws InvalidAllowedOrigin if the origin is not a valid origin or host
     */
    private function normalizeAllowedOrigin(string $origin): string
    {
        if (str_contains($origin, '://')) {
            $parsedOrigin = $this->parseOrigin($origin, strict: true);

            if (null === $parsedOrigin) {
                throw new InvalidAllowedOrigin($origin, \sprintf('The allowed origin "%s" is not a valid origin.', $origin));
            }

            return $parsedOrigin['origin'];
        }

        $host = strtolower($origin);

        // A host with a port or path would never match the host of an Origin header, so these must use a full origin
        if ('' === $host || parse_url('http://'.$host, \PHP_URL_HOST) !== $host) {
            throw new InvalidAllowedOrigin($origin, \sprintf('The allowed origin "%s" is not a valid host; to restrict an origin by its scheme or port, use a full origin such as "https://example.com:8443".', $origin));
        }

        return $host;
    }

    /**
     * @param bool $strict Whether to reject an origin with parts that are not allowed in an origin (such as a path)
     *
     * @return array{origin: non-empty-string, host: non-empty-string}|null
     */
    private function parseOrigin(string $origin, bool $strict = false): ?array
    {
        $parts = parse_url($origin);

        if (false === $parts || !isset($parts['scheme'], $parts['host']) || '' === $parts['host']) {
            return null;
        }

        if ($strict && (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment']) || !\in_array($parts['path'] ?? '', ['', '/'], true))) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);
        $port = $parts['port'] ?? self::DEFAULT_PORTS[$scheme] ?? null;

        return [
            'origin' => null === $port ? "{$scheme}://{$host}" : "{$scheme}://{$host}:{$port}",
            'host' => $host,
        ];
    }
}
