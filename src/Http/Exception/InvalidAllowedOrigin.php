<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class InvalidAllowedOrigin extends \InvalidArgumentException implements WebSocketException
{
    public function __construct(
        public readonly string $origin,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
