<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class MissingDependency extends \RuntimeException implements WebSocketException
{
    public function __construct(
        public readonly string $dependency,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
