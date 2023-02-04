<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Storage;

use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Exception\ReadOnlySession;
use BabDev\WebSocket\Server\Session\Reader\Reader;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorage;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

#[RequiresPhpExtension('session')]
final class ReadOnlyNativeSessionStorageTest extends TestCase
{
    private const SESSION_NAME = 'TestSession';

    private OptionsHandler $optionsHandler;

    private MockObject&Reader $reader;

    private MockObject&\SessionHandlerInterface $handler;

    private ReadOnlyNativeSessionStorage $storage;

    protected function setUp(): void
    {
        $this->optionsHandler = $this->createOptionsHandler();
        $this->optionsHandler->set('session.save_handler', 'user');
        $this->optionsHandler->set('session.name', self::SESSION_NAME);

        $this->reader = $this->createMock(Reader::class);
        $this->handler = $this->createMock(\SessionHandlerInterface::class);

        $this->storage = new ReadOnlyNativeSessionStorage(
            optionsHandler: $this->optionsHandler,
            reader: $this->reader,
            handler: $this->handler,
        );
    }

    public function testStartsAndClearsTheSession(): void
    {
        $now = time();
        $id = 'a1b2c3';

        $this->handler->expects($this->once())
            ->method('open')
            ->with($this->isType('string'), self::SESSION_NAME)
            ->willReturn(true);

        $this->handler->expects($this->once())
            ->method('read')
            ->with($id)
            ->willReturn('serialized_session_data');

        $this->reader->expects($this->once())
            ->method('read')
            ->willReturn(
                [
                    '_sf2_attributes' => [
                        'foo' => 'bar',
                        'messages.foo' => 'bar',
                        'data' => ['foo' => 'bar'],
                    ],
                    '_sf2_meta' => [
                        'u' => $now,
                        'c' => $now,
                        'l' => 0,
                    ],
                ],
            );

        $this->storage->setId($id);
        $this->storage->registerBag($bag = new AttributeBag('_sf2_attributes'));
        $this->storage->start();

        $this->assertTrue($this->storage->isStarted());

        $this->assertSame('bar', $bag->get('foo'));

        $this->storage->clear();

        $this->assertNull($bag->get('foo'));
    }

    public function testManagesTheSessionId(): void
    {
        $id = 'a1b2c3';

        $this->storage->setId($id);

        $this->assertSame($id, $this->storage->getId());
    }

    public function testFetchesTheSessionName(): void
    {
        $this->assertSame(self::SESSION_NAME, $this->storage->getName());
    }

    public function testForbidsSettingTheSessionName(): never
    {
        $this->expectException(ReadOnlySession::class);

        $this->storage->setName('invalid');
    }

    public function testForbidsRegeneratingTheSession(): never
    {
        $this->expectException(ReadOnlySession::class);

        $this->storage->regenerate();
    }

    #[DoesNotPerformAssertions]
    public function testSavesTheSession(): void
    {
        $this->storage->save();
    }

    #[DoesNotPerformAssertions]
    public function testCanRegisterBagsBeforeStartingTheSession(): void
    {
        $bag = new class() implements SessionBagInterface {
            public function getName(): string
            {
                return 'test';
            }

            public function initialize(array &$array): void
            {
            }

            public function getStorageKey(): string
            {
                return '_sf2_test';
            }

            public function clear(): mixed
            {
                return null;
            }
        };

        $this->storage->registerBag($bag);
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
