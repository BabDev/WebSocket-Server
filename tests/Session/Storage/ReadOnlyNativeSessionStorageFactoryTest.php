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

    public function testCreatesTheStorageInstance(): void
    {
        $factory = new ReadOnlyNativeSessionStorageFactory(
            optionsHandler: $this->createOptionsHandler(['session.save_handler' => 'user', 'session.name' => self::SESSION_NAME]),
            reader: $this->createStub(Reader::class),
            options: ['name' => 'CustomSession'],
            handler: $this->createStub(\SessionHandlerInterface::class),
        );

        $storage = $factory->createStorage(null);

        $this->assertInstanceOf(ReadOnlyNativeSessionStorage::class, $storage);
        $this->assertSame('CustomSession', $storage->getName());
    }

    /**
     * @param array<string, string> $ini
     */
    private function createOptionsHandler(array $ini = []): OptionsHandler
    {
        return new readonly class($ini) implements OptionsHandler {
            /**
             * @param array<string, string> $ini
             */
            public function __construct(private array $ini) {}

            public function get(string $option): string
            {
                return $this->ini[$option] ?? '';
            }

            public function set(string $option, string|int|float|bool|null $value): never
            {
                throw new \LogicException(\sprintf('The "%s" ini setting must not be changed.', $option));
            }
        };
    }
}
