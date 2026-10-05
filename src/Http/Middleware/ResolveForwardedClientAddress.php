<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Connection\RemoteAddress;
use BabDev\WebSocket\Server\Http\Exception\ConflictingForwardedHeaders;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\ServerMiddleware;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Request;

/**
 * The resolve forwarded client address server middleware replaces the connection's remote address with the address of
 * the client when the connection is made through a trusted reverse proxy.
 *
 * The client address is read from the "X-Forwarded-For" and/or "Forwarded" headers, following the same rules as the
 * trusted proxy support in Symfony's {@see Request} class. The proxy's address is kept in the "proxy_address" attribute.
 */
final readonly class ResolveForwardedClientAddress implements ServerMiddleware
{
    use ClosesConnectionWithResponse;

    /**
     * @var list<string>
     */
    private array $trustedProxies;

    private bool $trustRemoteAddress;

    /**
     * @param list<string>                   $trustedProxies   The addresses or subnets of the trusted proxies; the string "REMOTE_ADDR" trusts
     *                                                         the address of every connection, and "PRIVATE_SUBNETS" is replaced by {@see IpUtils::PRIVATE_SUBNETS}
     * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet A bit field of the headers to trust from the proxies; only the
     *                                                         {@see Request::HEADER_FORWARDED} and {@see Request::HEADER_X_FORWARDED_FOR} headers are used
     */
    public function __construct(
        private ServerMiddleware $middleware,
        array $trustedProxies,
        private int $trustedHeaderSet = Request::HEADER_X_FORWARDED_FOR,
    ) {
        $this->trustRemoteAddress = \in_array('REMOTE_ADDR', $trustedProxies, true);

        $proxies = [];

        foreach ($trustedProxies as $trustedProxy) {
            if ('PRIVATE_SUBNETS' === $trustedProxy || 'private_ranges' === $trustedProxy) {
                array_push($proxies, ...IpUtils::PRIVATE_SUBNETS);
            } elseif ('REMOTE_ADDR' !== $trustedProxy) {
                $proxies[] = $trustedProxy;
            }
        }

        $this->trustedProxies = $proxies;
    }

    /**
     * Handles a new connection to the server.
     *
     * @throws ConflictingForwardedHeaders if both trusted headers are present and resolve to different client addresses
     * @throws MissingRequest              if the HTTP request has not been parsed before this middleware is executed
     */
    public function onOpen(Connection $connection): void
    {
        /** @var RequestInterface|null $request */
        $request = $connection->getAttributeStore()->get(AttributeKey::HTTP_REQUEST);

        if (!$request instanceof RequestInterface) {
            throw new MissingRequest(\sprintf('The "%s" middleware requires the HTTP request has been processed. Ensure the "%s" middleware (or a custom middleware setting the "http.request" in the attribute store) has been run.', self::class, ParseHttpRequest::class));
        }

        /** @var non-empty-string|null $remoteAddress */
        $remoteAddress = $connection->getAttributeStore()->get(AttributeKey::REMOTE_ADDRESS);

        if (null !== $remoteAddress) {
            $trustedProxies = $this->trustRemoteAddress ? [...$this->trustedProxies, $remoteAddress] : $this->trustedProxies;

            if ([] !== $trustedProxies && IpUtils::checkIp($remoteAddress, $trustedProxies)) {
                try {
                    $clientAddress = $this->resolveClientAddress($request, $remoteAddress, $trustedProxies);
                } catch (ConflictingForwardedHeaders $exception) {
                    $this->close($connection, 400);

                    throw $exception;
                }

                if (null !== $clientAddress) {
                    $connection->getAttributeStore()->set(AttributeKey::PROXY_ADDRESS, $remoteAddress);
                    $connection->getAttributeStore()->set(AttributeKey::REMOTE_ADDRESS, $clientAddress);
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
     * @param non-empty-string $remoteAddress
     * @param list<string>     $trustedProxies
     *
     * @return non-empty-string|null The client address, or null if the request has no trusted forwarding headers
     *
     * @throws ConflictingForwardedHeaders if both trusted headers are present and resolve to different client addresses
     */
    private function resolveClientAddress(RequestInterface $request, string $remoteAddress, array $trustedProxies): ?string
    {
        $forwardedForValues = [];
        $forwardedValues = [];

        if (0 !== ($this->trustedHeaderSet & Request::HEADER_X_FORWARDED_FOR) && $request->hasHeader('X-Forwarded-For')) {
            foreach (explode(',', $request->getHeaderLine('X-Forwarded-For')) as $value) {
                $forwardedForValues[] = trim($value);
            }
        }

        if (0 !== ($this->trustedHeaderSet & Request::HEADER_FORWARDED) && $request->hasHeader('Forwarded')) {
            foreach (HeaderUtils::split($request->getHeaderLine('Forwarded'), ',;=') as $parts) {
                if (!\is_array($parts)) {
                    continue;
                }

                $value = HeaderUtils::combine($parts)['for'] ?? null;

                if (\is_string($value)) {
                    $forwardedValues[] = $value;
                }
            }
        }

        $forwardedForChain = $this->filterClientAddresses($forwardedForValues, $remoteAddress, $trustedProxies);
        $forwardedChain = $this->filterClientAddresses($forwardedValues, $remoteAddress, $trustedProxies);

        if ($forwardedChain === $forwardedForChain || [] === $forwardedForChain) {
            $chain = $forwardedChain;
        } elseif ([] === $forwardedChain) {
            $chain = $forwardedForChain;
        } else {
            throw new ConflictingForwardedHeaders('The request has both a trusted "Forwarded" header and a trusted "X-Forwarded-For" header, conflicting with each other. You should either configure your proxy to remove one of them, or configure the server to distrust the offending one.');
        }

        return $chain[0] ?? null;
    }

    /**
     * Normalizes the address chain from a forwarding header, removing invalid and trusted addresses.
     *
     * This follows the same rules as {@see Request::getClientIps()}: the chain is completed with the address of the
     * connection, the remaining untrusted addresses are returned with the client address first, and if every address
     * is trusted, the first trusted address is returned.
     *
     * @param list<string>     $addresses
     * @param non-empty-string $remoteAddress
     * @param list<string>     $trustedProxies
     *
     * @return list<non-empty-string>
     */
    private function filterClientAddresses(array $addresses, string $remoteAddress, array $trustedProxies): array
    {
        if ([] === $addresses) {
            return [];
        }

        $addresses[] = $remoteAddress;

        $untrustedAddresses = [];
        $firstTrustedAddress = null;

        foreach ($addresses as $address) {
            if (str_starts_with($address, '[')) {
                // Strip the brackets and port from IPv6 addresses
                $end = strpos($address, ']', 1);
                $address = false === $end ? $address : substr($address, 1, $end - 1);
            } elseif (strpos($address, '.') && 1 === substr_count($address, ':')) {
                // Strip the port from IPv4 addresses; an IPv6 address with an embedded IPv4 address has at least two colons
                $address = (string) strstr($address, ':', true);
            }

            $address = RemoteAddress::normalize($address);

            if (null === $address) {
                continue;
            }

            if (IpUtils::checkIp($address, $trustedProxies)) {
                $firstTrustedAddress ??= $address;

                continue;
            }

            $untrustedAddresses[] = $address;
        }

        if ([] !== $untrustedAddresses) {
            return array_reverse($untrustedAddresses);
        }

        return null !== $firstTrustedAddress ? [$firstTrustedAddress] : [];
    }
}
