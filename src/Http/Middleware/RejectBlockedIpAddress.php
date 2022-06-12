<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ClosesConnectionWithResponse;
use BabDev\WebSocket\Server\Exception\MissingDependency;
use BabDev\WebSocket\Server\ServerMiddleware;
use Symfony\Component\HttpFoundation\IpUtils;

/**
 * The reject blocked IP address server middleware checks the remote IP address against a list of blocked addresses and
 * closes the connection if blocked.
 *
 * The middleware allows rejecting IP addresses using a single address or a CIDR range.
 */
final class RejectBlockedIpAddress implements ServerMiddleware
{
    use ClosesConnectionWithResponse;

    /**
     * @var string[]
     */
    private array $blockedAddresses = [];

    /**
     * @throws MissingDependency if the `symfony/http-foundation` package is not installed
     */
    public function __construct(
        private readonly ServerMiddleware $middleware,
    ) {
        if (!class_exists(IpUtils::class)) {
            throw new MissingDependency('symfony/http-foundation', sprintf('The "%s" class requires the "symfony/http-foundation" package.', self::class));
        }
    }

    /**
     * Handles a new connection to the server.
     */
    public function onOpen(Connection $connection): void
    {
        if ($this->isConnectionRemoteAddressBlocked($connection)) {
            $this->close($connection, 403);

            return;
        }

        $this->middleware->onOpen($connection);
    }

    /**
     * Handles incoming data on the connection.
     */
    public function onMessage(Connection $connection, string $data): void
    {
        if (!$this->isConnectionRemoteAddressBlocked($connection)) {
            $this->middleware->onMessage($connection, $data);
        }
    }

    /**
     * Reacts to a connection being closed.
     */
    public function onClose(Connection $connection): void
    {
        if (!$this->isConnectionRemoteAddressBlocked($connection)) {
            $this->middleware->onClose($connection);
        }
    }

    /**
     * Reacts to an unhandled Throwable.
     */
    public function onError(Connection $connection, \Throwable $throwable): void
    {
        if (!$this->isConnectionRemoteAddressBlocked($connection)) {
            $this->middleware->onError($connection, $throwable);
        }
    }

    public function allowAddress(string $address): void
    {
        $this->blockedAddresses = array_filter($this->blockedAddresses, static fn (string $blockedAddress): bool => $blockedAddress !== $address);
    }

    public function blockAddress(string $address): void
    {
        $this->blockedAddresses[] = $address;
    }

    private function isConnectionRemoteAddressBlocked(Connection $connection): bool
    {
        $address = $connection->getAttributeStore()->get('remote_address');

        if (null === $address || [] === $this->blockedAddresses) {
            return false;
        }

        return IpUtils::checkIp($address, $this->blockedAddresses);
    }
}