<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Storage\Proxy;

use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\Session\Exception\ReadOnlySession;
use BabDev\WebSocket\Server\Session\Storage\Proxy\ReadOnlySessionHandlerProxy;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class ReadOnlySessionHandlerProxyTest extends TestCase
{
    private const SESSION_NAME = 'TestSession';

    private MockObject&\SessionHandlerInterface $handler;

    private OptionsHandler $optionsHandler;

    private ReadOnlySessionHandlerProxy $proxy;

    protected function setUp(): void
    {
        $this->handler = $this->createMock(\SessionHandlerInterface::class);

        $this->optionsHandler = $this->createOptionsHandler();
        $this->optionsHandler->set('session.save_handler', 'user');
        $this->optionsHandler->set('session.name', self::SESSION_NAME);

        $this->proxy = new ReadOnlySessionHandlerProxy($this->handler, $this->optionsHandler);
    }

    public function testRetrievesSessionIdAfterBeingSet(): void
    {
        $sessionId = 'a1b2c3';

        $this->proxy->setId($sessionId);

        $this->assertSame($sessionId, $this->proxy->getId());
    }

    public function testRetrievesSessionName(): void
    {
        $this->assertSame(self::SESSION_NAME, $this->proxy->getName());
    }

    public function testRaisesAnErrorIfTryingToChangeTheSessionName(): never
    {
        $this->expectException(ReadOnlySession::class);

        $this->proxy->setName('invalid');
    }

    public function testOpensTheSession(): void
    {
        $path = '/path/to/session';
        $name = self::SESSION_NAME;

        $this->handler->expects($this->once())
            ->method('open')
            ->with($path, $name)
            ->willReturn(true);

        $this->assertTrue($this->proxy->open($path, $name));
    }

    public function testClosesTheSession(): void
    {
        $this->handler->expects($this->once())
            ->method('close')
            ->willReturn(true);

        $this->assertTrue($this->proxy->close());
    }

    public function testReadsTheSessionData(): void
    {
        $data = 'serialized_session_data';
        $id = 'a1b2c3';

        $this->handler->expects($this->once())
            ->method('read')
            ->with($id)
            ->willReturn($data);

        $this->assertSame($data, $this->proxy->read($id));
    }

    public function testForbidsWritingSessionData(): never
    {
        $this->expectException(ReadOnlySession::class);

        $data = 'serialized_session_data';
        $id = 'a1b2c3';

        $this->proxy->write($id, $data);
    }

    public function testDestroysTheSession(): void
    {
        $id = 'a1b2c3';

        $this->handler->expects($this->once())
            ->method('destroy')
            ->with($id)
            ->willReturn(true);

        $this->assertTrue($this->proxy->destroy($id));
    }

    public function testRunsGarbageCollectionOnTheSession(): void
    {
        $lifetime = 1000;

        $this->handler->expects($this->once())
            ->method('gc')
            ->with($lifetime)
            ->willReturn(20);

        $this->assertSame(20, $this->proxy->gc($lifetime));
    }

    public function testCanValidateASessionId(): void
    {
        $id = 'a1b2c3';

        $this->assertTrue($this->proxy->validateId($id));
    }

    public function testForbidsUpdatingTheSessionTimestamp(): never
    {
        $this->expectException(ReadOnlySession::class);

        $data = 'serialized_session_data';
        $id = 'a1b2c3';

        $this->proxy->updateTimestamp($id, $data);
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
