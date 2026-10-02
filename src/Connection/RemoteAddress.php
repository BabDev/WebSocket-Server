<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

/**
 * Extracts a normalized IP address from the remote address URI of a socket connection.
 *
 * @internal
 */
final class RemoteAddress
{
    private const string IPV4_MAPPED_PREFIX = "\0\0\0\0\0\0\0\0\0\0\xff\xff";

    /**
     * Extracts the IP address from a remote address URI, such as "tcp://[::1]:8080" or "203.0.113.5:8080".
     *
     * The address is normalized so it can be compared against IP addresses and subnets:
     *
     * - The brackets around an IPv6 address are removed
     * - The zone ID of a link-local IPv6 address is removed (e.g. "fe80::1%en0" becomes "fe80::1")
     * - An IPv4-mapped IPv6 address is converted to its IPv4 address (e.g. "::ffff:203.0.113.5" becomes
     *   "203.0.113.5"), as dual-stack servers report IPv4 clients this way
     *
     * @return non-empty-string|null The IP address, or null if the URI does not contain one (such as a Unix socket)
     */
    public static function fromUri(string $uri): ?string
    {
        $host = parse_url((str_contains($uri, '://') ? '' : 'tcp://').$uri, \PHP_URL_HOST);

        if (!\is_string($host)) {
            return null;
        }

        $host = trim($host, '[]');

        if (str_contains($host, '%')) {
            $host = strstr($host, '%', true);
        }

        $address = filter_var($host, \FILTER_VALIDATE_IP);

        if (false === $address || '' === $address) {
            return null;
        }

        $packed = inet_pton($address);

        if (false !== $packed && 16 === \strlen($packed) && str_starts_with($packed, self::IPV4_MAPPED_PREFIX)) {
            $ipv4Address = inet_ntop(substr($packed, 12));

            if (false !== $ipv4Address && '' !== $ipv4Address) {
                return $ipv4Address;
            }
        }

        return $address;
    }
}
