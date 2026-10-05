<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http;

use BabDev\WebSocket\Server\Http\Exception\InvalidAllowedOrigin;

/**
 * Parses and normalizes origins for comparing the "Origin" header against the allowed origins.
 *
 * @internal
 */
final class Origin
{
    private const array DEFAULT_PORTS = [
        'http' => 80,
        'https' => 443,
        'ws' => 80,
        'wss' => 443,
    ];

    /**
     * @return non-empty-string
     *
     * @throws InvalidAllowedOrigin if the origin is not a valid origin or host
     */
    public static function normalizeAllowedOrigin(string $origin): string
    {
        if (str_contains($origin, '://')) {
            $parsedOrigin = self::parse($origin, strict: true);

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
    public static function parse(string $origin, bool $strict = false): ?array
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
