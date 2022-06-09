<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Http\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class MalformedRequest extends \UnexpectedValueException implements WebSocketException
{
}
