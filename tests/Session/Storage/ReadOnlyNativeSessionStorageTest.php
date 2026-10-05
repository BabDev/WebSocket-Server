<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Storage;

use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Exception\InvalidSession;
use BabDev\WebSocket\Server\Session\Exception\ReadOnlySession;
use BabDev\WebSocket\Server\Session\Reader\Reader;
use BabDev\WebSocket\Server\Session\Storage\ReadOnlyNativeSessionStorage;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\DoesNotPerformAssertions;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Session\Attribute\AttributeBag;
use Symfony\Component\HttpFoundation\Session\SessionBagInterface;

#[RequiresPhpExtension('session')]
final class ReadOnlyNativeSessionStorageTest extends TestCase
{
    private const string SESSION_NAME = 'TestSession';

    private const string SESSION_ID = 'k9q2v7m4x1c8b5n3z6w0r2t4ya';

    private readonly MockObject&Reader $reader;

    private readonly MockObject&\SessionHandlerInterface $handler;

    private readonly ReadOnlyNativeSessionStorage $storage;

    protected function setUp(): void
    {
        $optionsHandler = $this->createOptionsHandler();
        $optionsHandler->set('session.save_handler', 'user');
        $optionsHandler->set('session.name', self::SESSION_NAME);

        $this->reader = $this->createMock(Reader::class);
        $this->handler = $this->createMock(\SessionHandlerInterface::class);

        $this->storage = new ReadOnlyNativeSessionStorage(
            optionsHandler: $optionsHandler,
            reader: $this->reader,
            handler: $this->handler,
        );
    }

    public function testStartsAndClearsTheSession(): void
    {
        $now = time();
        $id = self::SESSION_ID;

        $this->handler->expects($this->once())
            ->method('open')
            ->with($this->isString(), self::SESSION_NAME)
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
        $bag = new class implements SessionBagInterface {
            public function getName(): string
            {
                return 'test';
            }

            public function initialize(array &$array): void {}

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

    /**
     * @return \Generator<string, array{string|null}>
     */
    public static function dataUnreadableSessionId(): \Generator
    {
        yield 'No session ID' => [null];

        yield 'Session ID which is too short' => ['a1b2c3'];

        yield 'Session ID which is too long' => [str_repeat('a', 251)];

        yield 'Session ID with a path' => ['../../../../etc/passwd/aaaaaaaaaaaa'];

        yield 'Session ID with invalid characters' => ['abcdefghijklmnopqrstuv;DROP'];
    }

    #[TestDox('Starts an empty session without reading the session data when the session ID is missing or invalid')]
    #[DataProvider('dataUnreadableSessionId')]
    public function testStartsAnEmptySessionForAnUnreadableSessionId(?string $id): void
    {
        $this->handler->expects($this->never())
            ->method('open');

        $this->handler->expects($this->never())
            ->method('read');

        $this->reader->expects($this->never())
            ->method('read');

        if (null !== $id) {
            $this->storage->setId($id);
        }

        $this->storage->registerBag($bag = new AttributeBag('_sf2_attributes'));

        $this->assertTrue($this->storage->start());
        $this->assertTrue($this->storage->isStarted());
        $this->assertSame([], $bag->all());
    }

    #[TestDox('Starts an empty session without reading the session data when the save handler rejects the session ID in strict mode')]
    public function testStartsAnEmptySessionWhenTheSessionIdIsRejectedInStrictMode(): void
    {
        /** @var MockObject&\SessionHandlerInterface&\SessionUpdateTimestampHandlerInterface $handler */
        $handler = $this->createMockForIntersectionOfInterfaces([\SessionHandlerInterface::class, \SessionUpdateTimestampHandlerInterface::class]);
        $handler->expects($this->once())
            ->method('open')
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('validateId')
            ->with(self::SESSION_ID)
            ->willReturn(false);

        $handler->expects($this->never())
            ->method('read');

        $this->handler->expects($this->never())
            ->method('read');

        $this->reader->expects($this->never())
            ->method('read');

        $storage = $this->createStorage($handler, strictMode: true);
        $storage->setId(self::SESSION_ID);
        $storage->registerBag($bag = new AttributeBag('_sf2_attributes'));

        $this->assertTrue($storage->start());
        $this->assertSame([], $bag->all());
    }

    #[TestDox('Does not validate the session ID with the save handler when strict mode is disabled')]
    public function testDoesNotValidateTheSessionIdWhenStrictModeIsDisabled(): void
    {
        /** @var MockObject&\SessionHandlerInterface&\SessionUpdateTimestampHandlerInterface $handler */
        $handler = $this->createMockForIntersectionOfInterfaces([\SessionHandlerInterface::class, \SessionUpdateTimestampHandlerInterface::class]);
        $handler->expects($this->once())
            ->method('open')
            ->willReturn(true);

        $handler->expects($this->never())
            ->method('validateId');

        $handler->expects($this->once())
            ->method('read')
            ->with(self::SESSION_ID)
            ->willReturn('');

        $this->handler->expects($this->never())
            ->method('read');

        $this->reader->expects($this->once())
            ->method('read')
            ->with('')
            ->willReturn([]);

        $storage = $this->createStorage($handler, strictMode: false);
        $storage->setId(self::SESSION_ID);

        $this->assertTrue($storage->start());
    }

    #[TestDox('Opens the save handler with the save path from the options handler')]
    public function testOpensTheSaveHandlerWithTheConfiguredSavePath(): void
    {
        $this->handler->expects($this->once())
            ->method('open')
            ->with('/var/lib/websocket-sessions', self::SESSION_NAME)
            ->willReturn(true);

        $this->handler->expects($this->once())
            ->method('read')
            ->willReturn('');

        $this->reader->expects($this->once())
            ->method('read')
            ->willReturn([]);

        $optionsHandler = $this->createOptionsHandler();
        $optionsHandler->set('session.save_handler', 'user');
        $optionsHandler->set('session.name', self::SESSION_NAME);
        $optionsHandler->set('session.save_path', '/var/lib/websocket-sessions');

        $storage = new ReadOnlyNativeSessionStorage(
            optionsHandler: $optionsHandler,
            reader: $this->reader,
            handler: $this->handler,
        );
        $storage->setId(self::SESSION_ID);
        $storage->start();
    }

    #[TestDox('Throws an exception when the save handler cannot read the session data')]
    public function testThrowsAnExceptionWhenTheSessionDataCannotBeRead(): void
    {
        $this->handler->expects($this->once())
            ->method('open')
            ->willReturn(true);

        $this->handler->expects($this->once())
            ->method('read')
            ->willReturn(false);

        $this->reader->expects($this->never())
            ->method('read');

        $this->expectException(InvalidSession::class);

        $this->storage->setId(self::SESSION_ID);
        $this->storage->start();
    }

    private function createStorage(\SessionHandlerInterface $handler, bool $strictMode): ReadOnlyNativeSessionStorage
    {
        $optionsHandler = $this->createOptionsHandler();
        $optionsHandler->set('session.save_handler', 'user');
        $optionsHandler->set('session.name', self::SESSION_NAME);

        return new ReadOnlyNativeSessionStorage(
            optionsHandler: $optionsHandler,
            reader: $this->reader,
            options: ['use_strict_mode' => $strictMode ? 1 : 0],
            handler: $handler,
        );
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
