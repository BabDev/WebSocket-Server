<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class MessageTooLarge extends \OverflowException implements WebSocketException
{
}
