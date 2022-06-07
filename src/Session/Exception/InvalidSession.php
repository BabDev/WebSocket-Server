<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class InvalidSession extends \RuntimeException implements WebSocketException
{
    public function __construct(
        public readonly string $sessionData,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
