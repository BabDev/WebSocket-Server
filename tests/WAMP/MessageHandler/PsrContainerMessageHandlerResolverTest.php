<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\MessageHandler;

use BabDev\WebSocket\Server\Tests\WAMP\MessageHandler\Fixtures\BasicMessageHandler;
use BabDev\WebSocket\Server\Tests\WAMP\MessageHandler\Fixtures\MissingInterfaceMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\InvalidRequest;
use BabDev\WebSocket\Server\WAMP\Exception\UnknownMessageHandler;
use BabDev\WebSocket\Server\WAMP\MessageHandler\PsrContainerMessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\HttpFoundation\ParameterBag;

final class PsrContainerMessageHandlerResolverTest extends TestCase
{
    public function testCannotResolveAMessageHandlerWhenTheRequestIsMissingRequiredAttributes(): void
    {
        $this->expectException(InvalidRequest::class);

        $request = new WAMPMessageRequest(new ParameterBag());

        (new PsrContainerMessageHandlerResolver(new Container()))->findMessageHandler($request);
    }

    public function testReturnsTheMessageHandlerWhenSetAsTheControllerRequestAttribute(): void
    {
        $attributes = new ParameterBag();
        $attributes->set('_controller', $handler = new BasicMessageHandler());

        $this->assertSame(
            $handler,
            (new PsrContainerMessageHandlerResolver(new Container()))->findMessageHandler(new WAMPMessageRequest($attributes)),
        );
    }

    public function testReturnsTheMessageHandlerWhenSetAsAStringInTheControllerRequestAttribute(): void
    {
        $attributes = new ParameterBag();
        $attributes->set('_controller', BasicMessageHandler::class);

        $container = new Container();
        $container->set(BasicMessageHandler::class, $handler = new BasicMessageHandler());

        $this->assertSame(
            $handler,
            (new PsrContainerMessageHandlerResolver($container))->findMessageHandler(new WAMPMessageRequest($attributes)),
        );
    }

    public function testCannotResolveAMessageHandlerWhenTheControllerRequestAttributeIsNotAStringOrHandlerClass(): void
    {
        $this->expectException(InvalidRequest::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', true);

        (new PsrContainerMessageHandlerResolver(new Container()))->findMessageHandler(new WAMPMessageRequest($attributes));
    }

    public function testCannotResolveAMessageHandlerWhenTheControllerRequestAttributeIsServiceIdThatDoesNotExistInTheContainer(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', \UnknownClass::class);

        (new PsrContainerMessageHandlerResolver(new Container()))->findMessageHandler(new WAMPMessageRequest($attributes));
    }

    public function testCannotResolveAMessageHandlerWhenTheControllerDoesNotImplementTheRequiredInterface(): void
    {
        $this->expectException(InvalidMessageHandler::class);

        $attributes = new ParameterBag();
        $attributes->set('_controller', MissingInterfaceMessageHandler::class);

        $container = new Container();
        $container->set(MissingInterfaceMessageHandler::class, new MissingInterfaceMessageHandler());

        (new PsrContainerMessageHandlerResolver($container))->findMessageHandler(new WAMPMessageRequest($attributes));
    }
}

final class ServiceNotFound extends \InvalidArgumentException implements NotFoundExceptionInterface
{
}

final class Container implements ContainerInterface
{
    private array $services = [];

    /**
     * @param string $id
     */
    public function get($id)
    {
        return $this->services[$id] ?? throw new ServiceNotFound(sprintf('Service "%s" does not exist.', $id));
    }

    /**
     * @param string $id
     */
    public function has($id): bool
    {
        return \array_key_exists($id, $this->services);
    }

    public function set(string $id, object $service): void
    {
        $this->services[$id] = $service;
    }
}
