<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP\Exception;

use BabDev\WebSocket\Server\WAMP\MessageType;
use BabDev\WebSocket\Server\WebSocketException;

class TopicAlreadyRegistered extends \RuntimeException implements WebSocketException
{
}