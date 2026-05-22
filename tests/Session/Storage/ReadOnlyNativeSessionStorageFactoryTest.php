<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Storage;

use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Reader\Reader;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorage;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorageFactory;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

#[RequiresPhpExtension('session')]
final class ReadOnlyNativeSessionStorageFactoryTest extends TestCase
{
    private const string SESSION_NAME = 'TestSession';

    private readonly ReadOnlyNativeSessionStorageFactory $factory;

    protected function setUp(): void
    {
        $optionsHandler = $this->createOptionsHandler();
        $optionsHandler->set('session.save_handler', 'user');
        $optionsHandler->set('session.name', self::SESSION_NAME);

        $this->factory = new ReadOnlyNativeSessionStorageFactory(
            optionsHandler: $optionsHandler,
            reader: $this->createStub(Reader::class),
            handler: $this->createStub(\SessionHandlerInterface::class),
        );
    }

    public function testCreatesTheStorageInstance(): void
    {
        $this->assertInstanceOf(ReadOnlyNativeSessionStorage::class, $this->factory->createStorage(null));
    }

    private function createOptionsHandler(): OptionsHandler
    {
        return new class implements OptionsHandler {
            /**
             * @var array<string, string>
             */
            private array $options = [];

            public function get(string $option): string
            {
                return $this->options[$option] ?? '';
            }

            public function set(string $option, string|int|float|bool|null $value): string
            {
                return $this->options[$option] = (string) $value;
            }
        };
    }
}
