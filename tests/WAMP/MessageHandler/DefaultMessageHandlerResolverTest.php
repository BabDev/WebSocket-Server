<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\MessageHandler;

use BabDev\WebSocket\Server\Tests\WAMP\MessageHandler\Fixtures\AdvancedMessageHandler;
use BabDev\WebSocket\Server\Tests\WAMP\MessageHandler\Fixtures\BasicMessageHandler;
use BabDev\WebSocket\Server\Tests\WAMP\MessageHandler\Fixtures\MissingInterfaceMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\CannotInstantiateMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidRequest;
use BabDev\WebSocket\Server\WAMP\Exception\UnknownMessageHandler;
use BabDev\WebSocket\Server\WAMP\MessageHandler\DefaultMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\ParameterBag;

final class DefaultMessageHandlerResolverTest extends TestCase
{
    public function testCannotResolveAMessageHandlerWhenTheRequestIsMissingRequiredAttributes(): void
    {
        $this->expectException(InvalidRequest::class);

        $request = new WAMPMessageRequest(new ParameterBag());

        (new DefaultMessageHandlerResolver())->findMessageHandler($request);
    }

    public function testReturnsTheMessageHandlerWhenSetAsTheControllerRequestAttribute(): void
    {
        $attributes = new ParameterBag();
        $attributes->set('_controller', $handler = new BasicMessageHandler());

        $this->assertSame(
            $handler,
            (new DefaultMessageHandlerResolver())->findMessageHandler(new WAMPMessageRequest($attributes)),
        );
    }

    public function testReturnsTheMessageHandlerWhenSetAsAClassStringInTheControllerRequestAttribute(): void
    {
        $attributes = new ParameterBag();
        $attributes->set('_controller', BasicMessageHandler::class);

        $this->assertInstanceOf(
            BasicMessageHandler::class,
            (new DefaultMessageHandlerResolver())->findMessageHandler(new WAMPMessageRequest($attributes)),
        );
    }

    public function testCannotResolveAMessageHandlerWhenTheControllerRequestAttributeIsNotAStringOrHandlerClass(): void
    {
        $this->expectException(InvalidRequest::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', true);

        (new DefaultMessageHandlerResolver())->findMessageHandler(new WAMPMessageRequest($attributes));
    }

    public function testCannotResolveAMessageHandlerWhenTheControllerRequestAttributeIsANonExistingClass(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', \UnknownClass::class);

        (new DefaultMessageHandlerResolver())->findMessageHandler(new WAMPMessageRequest($attributes));
    }

    public function testCannotResolveAMessageHandlerWithRequiredConstructorArguments(): void
    {
        $this->expectException(CannotInstantiateMessageHandler::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', AdvancedMessageHandler::class);

        (new DefaultMessageHandlerResolver())->findMessageHandler(new WAMPMessageRequest($attributes));
    }

    public function testCannotResolveAMessageHandlerWhenTheControllerDoesNotImplementTheRequiredInterface(): void
    {
        $this->expectException(InvalidMessageHandler::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', MissingInterfaceMessageHandler::class);

        (new DefaultMessageHandlerResolver())->findMessageHandler(new WAMPMessageRequest($attributes));
    }
}
