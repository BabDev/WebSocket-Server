<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MessageTooLarge;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

/**
 * The Guzzle request parser is an implementation of the {@see RequestParser} using the `guzzle/psr7` library.
 */
final class GuzzleRequestParser implements RequestParser
{
    public function __construct(
        /**
         * The maximum number of bytes from the request that can be parsed.
         *
         * This is a security measure to help prevent attacks.
         */
        public int $maxRequestSize = 4096
    ) {}

    /**
     * @throws MalformedRequest if the HTTP request cannot be parsed
     * @throws MessageTooLarge  if the HTTP request is bigger than the maximum allowed size
     */
    public function parse(Connection $connection, string $data): ?RequestInterface
    {
        $buffer = $connection->getAttributeStore()->get('http.buffer', '');
        $buffer .= $data;

        $connection->getAttributeStore()->set('http.buffer', $buffer);

        if (\strlen($buffer) > $this->maxRequestSize) {
            $connection->getAttributeStore()->remove('http.buffer');

            throw new MessageTooLarge("Maximum buffer size of {$this->maxRequestSize} exceeded parsing HTTP header");
        }

        if (!$this->isEndOfMessage($buffer)) {
            return null;
        }

        try {
            $request = Message::parseRequest($buffer);
        } catch (\InvalidArgumentException $exception) {
            throw new MalformedRequest('The incoming request could not be parsed', previous: $exception);
        } finally {
            $connection->getAttributeStore()->remove('http.buffer');
        }

        return $request;
    }

    /**
     * Determine if the message has been buffered as per the HTTP specification.
     */
    private function isEndOfMessage(string $message): bool
    {
        return str_contains($message, RequestParser::END_OF_MESSAGE_MARKER);
    }
}
