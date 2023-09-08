<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

use BabDev\WebSocket\Server\Connection;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Response;

/**
 * The close connection with response trait provides a convenience method to close a {@see Connection} after
 * sending an HTTP message to it.
 */
trait ClosesConnectionWithResponse
{
    /**
     * @param int $code Status code
     * @param array<string, string|string[]> $headers Response headers
     */
    private function close(Connection $connection, int $code = 400, array $headers = []): void
    {
        $connection->send(Message::toString(new Response($code, $headers)));
        $connection->close();
    }
}
