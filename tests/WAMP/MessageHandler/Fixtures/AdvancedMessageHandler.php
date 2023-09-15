<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\MessageHandler\Fixtures;

use BabDev\WebSocket\Server\MessageHandler;

final readonly class AdvancedMessageHandler implements MessageHandler
{
    public function __construct(public string $name) {}
}
