<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class RouteNotFound extends \RuntimeException implements WebSocketException
{
}
