<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session\Storage;

use BabDev\WebSocket\Server\IniOptionsHandler;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Reader\Reader;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Storage\MetadataBag;
use Symfony\Component\HttpFoundation\Session\Storage\Proxy\AbstractProxy;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageInterface;

final class ReadOnlyNativeSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private readonly OptionsHandler $optionsHandler = new IniOptionsHandler(),
        private readonly ?Reader $reader = null,
        private readonly array $options = [],
        private readonly AbstractProxy|\SessionHandlerInterface|null $handler = null,
        private readonly ?MetadataBag $metaBag = null
    ) {
    }

    public function createStorage(?Request $request): SessionStorageInterface
    {
        return new ReadOnlyNativeSessionStorage($this->optionsHandler, $this->reader, $this->options, $this->handler, $this->metaBag);
    }
}
