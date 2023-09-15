<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use Symfony\Component\HttpFoundation\ParameterBag;

/**
 * The WAMP message request class encapsulates the matched route information for an incoming WAMP message.
 */
final readonly class WAMPMessageRequest
{
    public function __construct(public ParameterBag $attributes) {}
}
