<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Session;

use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\SessionStorageFactoryInterface;

final readonly class SessionFactory implements SessionFactoryInterface
{
    public function __construct(private SessionStorageFactoryInterface $storageFactory) {}

    public function createSession(): SessionInterface
    {
        return new Session($this->storageFactory->createStorage(null));
    }
}
