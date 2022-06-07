<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class SessionMisconfigured extends \RuntimeException implements WebSocketException
{
}
