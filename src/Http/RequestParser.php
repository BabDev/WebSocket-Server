<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MessageTooLarge;
use Psr\Http\Message\RequestInterface;

/**
 * The request parser interface is responsible for converting an HTTP request into a {@see RequestInterface} object.
 */
interface RequestParser
{
    final public const END_OF_MESSAGE_MARKER = "\r\n\r\n";

    /**
     * @throws MalformedRequest if the HTTP request cannot be parsed
     * @throws MessageTooLarge  if the HTTP request is bigger than the maximum allowed size
     */
    public function parse(Connection $connection, string $data): ?RequestInterface;
}
