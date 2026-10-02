<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Exception;

/**
 * Thrown when a client attempts to register more CURIE prefixes than the server allows for a connection.
 */
class PrefixLimitExceeded extends InvalidMessage
{
    /**
     * @param positive-int $limit
     */
    public function __construct(
        public readonly int $limit,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
