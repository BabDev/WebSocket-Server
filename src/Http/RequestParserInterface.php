<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http;

use BabDev\WebSocket\Server\ConnectionInterface;
use Psr\Http\Message\RequestInterface;

/**
 * The request parser interface is responsible for converting an HTTP request into a {@see RequestInterface} object.
 */
interface RequestParserInterface
{
    final public const END_OF_MESSAGE_MARKER = "\r\n\r\n";

    /**
     * @throws \OverflowException if the HTTP request is bigger than the maximum allowed size
     */
    public function parse(ConnectionInterface $connection, string $data): ?RequestInterface;
}
