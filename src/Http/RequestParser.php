<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http;

use BabDev\WebSocket\Server\ConnectionInterface;
use GuzzleHttp\Psr7\Message;
use Psr\Http\Message\RequestInterface;

final class RequestParser implements RequestParserInterface
{
    /**
     * The maximum number of bytes from the request that can be parsed.
     *
     * This is a security measure to help prevent attacks.
     */
    public int $maxRequestSize = 4096;

    public function parse(ConnectionInterface $connection, string $data): ?RequestInterface
    {
        $buffer = $connection->getAttributeStore()->get('http.buffer', '');
        $buffer .= $data;

        $connection->getAttributeStore()->set('http.buffer', $buffer);

        if (\strlen($buffer) > $this->maxRequestSize) {
            throw new \OverflowException("Maximum buffer size of {$this->maxRequestSize} exceeded parsing HTTP header");
        }

        if (!$this->isEndOfMessage($buffer)) {
            return null;
        }

        $request = Message::parseRequest($buffer);

        $connection->getAttributeStore()->remove('http.buffer');

        return $request;
    }

    /**
     * Determine if the message has been buffered as per the HTTP specification.
     */
    private function isEndOfMessage(string $message): bool
    {
        return str_contains($message, RequestParserInterface::END_OF_MESSAGE_MARKER);
    }
}
