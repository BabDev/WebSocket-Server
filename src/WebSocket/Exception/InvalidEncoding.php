<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WebSocket\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class InvalidEncoding extends \DomainException implements WebSocketException
{
}
