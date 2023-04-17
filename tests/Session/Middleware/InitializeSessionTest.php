<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\OptionsHandler;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Session\Middleware\InitializeSession;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use Symfony\Component\HttpFoundation\Session\SessionFactoryInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

final class InitializeSessionTest extends TestCase
{
    private MockObject&ServerMiddleware $decoratedMiddleware;

    private MockObject&SessionFactoryInterface $sessionFactory;

    private OptionsHandler $optionsHandler;

    private InitializeSession $middleware;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);
        $this->sessionFactory = $this->createMock(SessionFactoryInterface::class);
        $this->optionsHandler = $this->createOptionsHandler();

        $this->middleware = new InitializeSession($this->decoratedMiddleware, $this->sessionFactory, $this->optionsHandler);
    }

    #[TestDox('Handles a new connection being opened without any cookies')]
    public function testOnOpenWithoutRequestCookies(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Cookie')
            ->willReturn(false);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('session', $session);

        $this->sessionFactory->expects($this->once())
            ->method('createSession')
            ->willReturn($session);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened with a session cookie')]
    public function testOnOpenWithRequestCookies(): void
    {
        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Cookie')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('getHeader')
            ->with('Cookie')
            ->willReturn(['vote_2020_banner=true; PHPSESSID=1pnikdt557ibm405phf4iafsie']);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('getName')
            ->willReturn('PHPSESSID');

        $session->expects($this->once())
            ->method('setId')
            ->with('1pnikdt557ibm405phf4iafsie');

        $session->expects($this->once())
            ->method('start');

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('session', $session);

        $this->sessionFactory->expects($this->once())
            ->method('createSession')
            ->willReturn($session);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->optionsHandler->set('session.auto_start', 1);

        $this->middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened with an invalid cookie header')]
    public function testOnOpenWithInvalidCookieHeader(): void
    {
        $this->expectException(\RuntimeException::class);

        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('hasHeader')
            ->with('Cookie')
            ->willReturn(true);

        $request->expects($this->once())
            ->method('getHeader')
            ->with('Cookie')
            ->willReturn(['vote_2020_banner=true; PHPSESSID']);

        /** @var MockObject&SessionInterface $session */
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->once())
            ->method('getName')
            ->willReturn('PHPSESSID');

        $session->expects($this->never())
            ->method('setId');

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        $attributeStore->expects($this->never())
            ->method('set');

        $this->sessionFactory->expects($this->once())
            ->method('createSession')
            ->willReturn($session);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->onOpen($connection);
    }

    #[TestDox('Handles a new connection being opened when required middleware have not run before this middleware')]
    public function testOnOpenWithoutRequest(): void
    {
        $this->expectException(MissingRequest::class);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn(null);

        $this->sessionFactory->expects($this->never())
            ->method('createSession');

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->middleware->onOpen($connection);
    }

    #[TestDox('Handles incoming data on the connection')]
    public function testOnMessage(): void
    {
        $data = 'Testing';

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $data);

        $this->middleware->onMessage($connection, $data);
    }

    #[TestDox('Closes the connection')]
    public function testOnClose(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->middleware->onClose($connection);
    }

    #[TestDox('Handles an error')]
    public function testOnError(): void
    {
        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $error = new \Exception('Testing');

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $error);

        $this->middleware->onError($connection, $error);
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
