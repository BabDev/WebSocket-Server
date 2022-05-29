<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

abstract class MessageType
{
    final public const WELCOME = 0;
    final public const PREFIX = 1;
    final public const CALL = 2;
    final public const CALL_RESULT = 3;
    final public const CALL_ERROR = 4;
    final public const SUBSCRIBE = 5;
    final public const UNSUBSCRIBE = 6;
    final public const PUBLISH = 7;
    final public const EVENT = 8;

    final private function __construct()
    {
    }
}
