<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Storage;

use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Reader\Reader;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorage;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorageFactory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('session')]
final class ReadOnlyNativeSessionStorageFactoryTest extends TestCase
{
    private const SESSION_NAME = 'TestSession';

    private const OPTIONS = [];

    private OptionsHandler $optionsHandler;

    private MockObject&Reader $reader;

    private MockObject&\SessionHandlerInterface $handler;

    private ReadOnlyNativeSessionStorageFactory $factory;

    protected function setUp(): void
    {
        $this->optionsHandler = $this->createOptionsHandler();
        $this->optionsHandler->set('session.save_handler', 'user');
        $this->optionsHandler->set('session.name', self::SESSION_NAME);

        $this->reader = $this->createMock(Reader::class);
        $this->handler = $this->createMock(\SessionHandlerInterface::class);

        $this->factory = new ReadOnlyNativeSessionStorageFactory($this->optionsHandler, $this->reader, self::OPTIONS, $this->handler);
    }

    public function testCreatesTheStorageInstance(): void
    {
        $this->assertInstanceOf(ReadOnlyNativeSessionStorage::class, $this->factory->createStorage(null));
    }

    private function createOptionsHandler(): OptionsHandler
    {
        return new class() implements OptionsHandler {
            private array $options = [];

            public function get(string $option): mixed
            {
                return $this->options[$option] ?? null;
            }

            public function set(string $option, mixed $value): void
            {
                $this->options[$option] = $value;
            }
        };
    }
}
