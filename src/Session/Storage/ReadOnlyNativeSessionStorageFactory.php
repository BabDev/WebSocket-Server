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

final readonly class ReadOnlyNativeSessionStorageFactory implements SessionStorageFactoryInterface
{
    public function __construct(
        private OptionsHandler $optionsHandler = new IniOptionsHandler(),
        private ?Reader $reader = null,
        private array $options = [],
        private AbstractProxy|\SessionHandlerInterface|null $handler = null,
        private ?MetadataBag $metaBag = null
    ) {}

    public function createStorage(?Request $request): SessionStorageInterface
    {
        return new ReadOnlyNativeSessionStorage($this->optionsHandler, $this->reader, $this->options, $this->handler, $this->metaBag);
    }
}
