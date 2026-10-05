<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Storage\Proxy;

use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Exception\ReadOnlySession;
use BabDev\WebSocket\Server\Session\Storage\Proxy\ReadOnlySessionHandlerProxy;
use PHPUnit\Framework\TestCase;

final class ReadOnlySessionHandlerProxyTest extends TestCase
{
    private const string SESSION_NAME = 'TestSession';

    public function testRetrievesSessionIdAfterBeingSet(): void
    {
        $sessionId = 'a1b2c3';

        $proxy = $this->createProxy();
        $proxy->setId($sessionId);

        $this->assertSame($sessionId, $proxy->getId());
    }

    public function testRetrievesSessionName(): void
    {
        $this->assertSame(self::SESSION_NAME, $this->createProxy()->getName());
    }

    public function testRaisesAnErrorIfTryingToChangeTheSessionName(): never
    {
        $this->expectException(ReadOnlySession::class);

        $this->createProxy()->setName('invalid');
    }

    public function testOpensTheSession(): void
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);

        $path = '/path/to/session';
        $name = self::SESSION_NAME;

        $handler->expects($this->once())
            ->method('open')
            ->with($path, $name)
            ->willReturn(true);

        $this->assertTrue($this->createProxy($handler)->open($path, $name));
    }

    public function testClosesTheSession(): void
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);

        $handler->expects($this->once())
            ->method('close')
            ->willReturn(true);

        $this->assertTrue($this->createProxy($handler)->close());
    }

    public function testReadsTheSessionData(): void
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);

        $data = 'serialized_session_data';
        $id = 'a1b2c3';

        $handler->expects($this->once())
            ->method('read')
            ->with($id)
            ->willReturn($data);

        $this->assertSame($data, $this->createProxy($handler)->read($id));
    }

    public function testForbidsWritingSessionData(): never
    {
        $this->expectException(ReadOnlySession::class);

        $data = 'serialized_session_data';
        $id = 'a1b2c3';

        $this->createProxy()->write($id, $data);
    }

    public function testForbidsDestroyingTheSession(): never
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);

        $handler->expects($this->never())
            ->method('destroy');

        $this->expectException(ReadOnlySession::class);

        $this->createProxy($handler)->destroy('a1b2c3');
    }

    public function testForbidsRunningGarbageCollectionOnTheSession(): never
    {
        $handler = $this->createMock(\SessionHandlerInterface::class);

        $handler->expects($this->never())
            ->method('gc');

        $this->expectException(ReadOnlySession::class);

        $this->createProxy($handler)->gc(1000);
    }

    public function testCanValidateASessionId(): void
    {
        $id = 'a1b2c3';

        $this->assertTrue($this->createProxy()->validateId($id));
    }

    public function testForbidsUpdatingTheSessionTimestamp(): never
    {
        $this->expectException(ReadOnlySession::class);

        $data = 'serialized_session_data';
        $id = 'a1b2c3';

        $this->createProxy()->updateTimestamp($id, $data);
    }

    private function createProxy(?\SessionHandlerInterface $handler = null): ReadOnlySessionHandlerProxy
    {
        $optionsHandler = $this->createOptionsHandler();
        $optionsHandler->set('session.save_handler', 'user');
        $optionsHandler->set('session.name', self::SESSION_NAME);

        return new ReadOnlySessionHandlerProxy($handler ?? $this->createStub(\SessionHandlerInterface::class), $optionsHandler);
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
