<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class ReadOnlySession extends \RuntimeException implements WebSocketException {}
