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

    public function testStartsAndClearsTheSession(): void
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $reader = $this->createMock(Reader::class);

        $now = time();
        $id = self::SESSION_ID;

        $handler->expects($this->once())
            ->method('open')
            ->with($this->isString(), self::SESSION_NAME)
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('read')
            ->with($id)
            ->willReturn('serialized_session_data');

        $reader->expects($this->once())
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

        $storage = $this->createStorage($reader, $handler);
        $storage->setId($id);
        $storage->registerBag($bag = new AttributeBag('_sf2_attributes'));
        $storage->start();

        $this->assertTrue($storage->isStarted());

        $this->assertSame('bar', $bag->get('foo'));

        $storage->clear();

        $this->assertNull($bag->get('foo'));
    }

    public function testManagesTheSessionId(): void
    {
        $id = 'a1b2c3';

        $storage = $this->createStorage();

        $storage->setId($id);

        $this->assertSame($id, $storage->getId());
    }

    public function testFetchesTheSessionName(): void
    {
        $this->assertSame(self::SESSION_NAME, $this->createStorage()->getName());
    }

    public function testForbidsSettingTheSessionName(): never
    {
        $this->expectException(ReadOnlySession::class);

        $this->createStorage()->setName('invalid');
    }

    public function testForbidsRegeneratingTheSession(): never
    {
        $this->expectException(ReadOnlySession::class);

        $this->createStorage()->regenerate();
    }

    #[DoesNotPerformAssertions]
    public function testSavesTheSession(): void
    {
        $this->createStorage()->save();
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

        $this->createStorage()->registerBag($bag);
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
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $reader = $this->createMock(Reader::class);

        $handler->expects($this->never())
            ->method('open');

        $handler->expects($this->never())
            ->method('read');

        $reader->expects($this->never())
            ->method('read');

        $storage = $this->createStorage($reader, $handler);

        if (null !== $id) {
            $storage->setId($id);
        }

        $storage->registerBag($bag = new AttributeBag('_sf2_attributes'));

        $this->assertTrue($storage->start());
        $this->assertTrue($storage->isStarted());
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

        $reader = $this->createMock(Reader::class);
        $reader->expects($this->never())
            ->method('read');

        $storage = $this->createStorage($reader, $handler, strictMode: true);
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

        $reader = $this->createMock(Reader::class);
        $reader->expects($this->once())
            ->method('read')
            ->with('')
            ->willReturn([]);

        $storage = $this->createStorage($reader, $handler, strictMode: false);
        $storage->setId(self::SESSION_ID);

        $this->assertTrue($storage->start());
    }

    #[TestDox('Opens the save handler with the save path from the options handler')]
    public function testOpensTheSaveHandlerWithTheConfiguredSavePath(): void
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $reader = $this->createMock(Reader::class);

        $handler->expects($this->once())
            ->method('open')
            ->with('/var/lib/websocket-sessions', self::SESSION_NAME)
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('read')
            ->willReturn('');

        $reader->expects($this->once())
            ->method('read')
            ->willReturn([]);

        $storage = $this->createStorage($reader, $handler, savePath: '/var/lib/websocket-sessions');
        $storage->setId(self::SESSION_ID);
        $storage->start();
    }

    #[TestDox('Throws an exception when the save handler cannot read the session data')]
    public function testThrowsAnExceptionWhenTheSessionDataCannotBeRead(): void
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);
        $reader = $this->createMock(Reader::class);

        $handler->expects($this->once())
            ->method('open')
            ->willReturn(true);

        $handler->expects($this->once())
            ->method('read')
            ->willReturn(false);

        $reader->expects($this->never())
            ->method('read');

        $this->expectException(InvalidSession::class);

        $storage = $this->createStorage($reader, $handler);
        $storage->setId(self::SESSION_ID);
        $storage->start();
    }

    private function createStorage(?Reader $reader = null, ?\SessionHandlerInterface $handler = null, ?bool $strictMode = null, ?string $savePath = null): ReadOnlyNativeSessionStorage
    {
        $optionsHandler = $this->createOptionsHandler();
        $optionsHandler->set('session.save_handler', 'user');
        $optionsHandler->set('session.name', self::SESSION_NAME);

        if (null !== $savePath) {
            $optionsHandler->set('session.save_path', $savePath);
        }

        return new ReadOnlyNativeSessionStorage(
            optionsHandler: $optionsHandler,
            reader: $reader ?? $this->createStub(Reader::class),
            options: null !== $strictMode ? ['use_strict_mode' => $strictMode ? 1 : 0] : [],
            handler: $handler ?? $this->createStub(\SessionHandlerInterface::class),
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
