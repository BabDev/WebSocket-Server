<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Exception;

use BabDev\WebSocket\Server\WAMP\MessageType;
use BabDev\WebSocket\Server\WebSocketException;

class UnsupportedMessageType extends \RuntimeException implements WebSocketException
{
    /**
     * @phpstan-param MessageType::* $messageType
     */
    public function __construct(
        public readonly int $messageType,
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
