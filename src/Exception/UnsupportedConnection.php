<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Exception;

use BabDev\WebSocket\Server\WebSocketException;

class UnsupportedConnection extends \RuntimeException implements WebSocketException {}
