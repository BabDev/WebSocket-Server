<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class CannotInstantiateMessageHandler extends \RuntimeException implements WebSocketException
{
    /**
     * @phpstan-param class-string $handler
     */
    public function __construct(
        public readonly string $handler,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
