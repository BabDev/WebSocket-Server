<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Http\Exception\InvalidRequestTimeout;
use BabDev\WebSocket\Server\Http\Exception\MalformedRequest;
use BabDev\WebSocket\Server\Http\Exception\MessageTooLarge;
use BabDev\WebSocket\Server\Http\Middleware\ParseHttpRequest;
use BabDev\WebSocket\Server\Http\RequestParser;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Tests\Fixtures\RecordingConnection;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;
use React\EventLoop\LoopInterface;
use React\EventLoop\TimerInterface;

final class ParseHttpRequestTest extends TestCase
{
    #[TestDox('Handles a new connection being opened')]
    public function testOnOpen(): void
    {
        $attributeStore = new ArrayAttributeStore();

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $this->createMiddleware()->onOpen($connection);

        $this->assertFalse($attributeStore->get('http.headers_received'));
    }

    #[TestDox('Handles incoming data on the connection when the HTTP message has not yet been parsed')]
    public function testOnMessageWhenHttpMessageNotYetParsed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&RequestParser $requestParser */
        $requestParser = $this->createMock(RequestParser::class);

        $message = 'Testing';

        /** @var Stub&RequestInterface $request */
        $request = $this->createStub(RequestInterface::class);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->exactly(3))
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $requestParser->expects($this->once())
            ->method('parse')
            ->with($connection, $message)
            ->willReturn($request);

        $decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $this->createMiddleware($decoratedMiddleware, $requestParser)->onMessage($connection, $message);

        $this->assertTrue($attributeStore->get('http.headers_received'));
        $this->assertSame($request, $attributeStore->get('http.request'));
    }

    #[TestDox('Handles incoming data on the connection when the HTTP message has been parsed')]
    public function testOnMessageWhenHttpMessageHasBeenParsed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&RequestParser $requestParser */
        $requestParser = $this->createMock(RequestParser::class);

        $message = 'Testing';

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', true);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $requestParser->expects($this->never())
            ->method('parse');

        $decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, $message);

        $this->createMiddleware($decoratedMiddleware, $requestParser)->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection when a malformed request body is received')]
    public function testOnMessageWhenHttpMessageIsInvalid(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&RequestParser $requestParser */
        $requestParser = $this->createMock(RequestParser::class);

        $message = 'Testing';

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $requestParser->expects($this->once())
            ->method('parse')
            ->with($connection, $message)
            ->willThrowException(new MalformedRequest('Testing'));

        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->expectException(MalformedRequest::class);

        $this->createMiddleware($decoratedMiddleware, $requestParser)->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection when a buffer overflow is reached while processing incoming data')]
    public function testOnMessageWhenHttpMessageOverflows(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&RequestParser $requestParser */
        $requestParser = $this->createMock(RequestParser::class);

        $message = 'Testing';

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $requestParser->expects($this->once())
            ->method('parse')
            ->with($connection, $message)
            ->willThrowException(new MessageTooLarge('Testing'));

        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->expectException(MessageTooLarge::class);

        $this->createMiddleware($decoratedMiddleware, $requestParser)->onMessage($connection, $message);
    }

    #[TestDox('Closes the connection when the request has been parsed')]
    public function testOnCloseWhenRequestParsed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', true);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->createMiddleware($decoratedMiddleware)->onClose($connection);
    }

    #[TestDox('Handles an error when the request has been parsed')]
    public function testOnErrorWhenRequestParsed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $exception = new \RuntimeException('Testing');

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', true);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $this->createMiddleware($decoratedMiddleware)->onError($connection, $exception);
    }

    #[TestDox('Handles an error when the request has not been parsed')]
    public function testOnErrorWhenRequestNotParsed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $exception = new \RuntimeException('Testing');

        $attributeStore = new ArrayAttributeStore();
        $attributeStore->set('http.headers_received', false);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $decoratedMiddleware->expects($this->never())
            ->method('onError');

        $connection->expects($this->once())
            ->method('send');

        $connection->expects($this->once())
            ->method('close');

        $this->createMiddleware($decoratedMiddleware)->onError($connection, $exception);
    }

    #[TestDox('Closes the connection with a 408 response when the request is not received before the request timeout')]
    public function testRequestTimeoutClosesConnection(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $timerCallback = null;

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addTimer')
            ->with(5.0, $this->isCallable())
            ->willReturnCallback(function (float $interval, callable $callback) use (&$timerCallback): TimerInterface {
                $timerCallback = $callback;

                return $this->createStub(TimerInterface::class);
            });

        $decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $connection = new RecordingConnection();
        $connection->getAttributeStore()->set('http.buffer', 'GET / HTTP/1.1');

        $middleware = $this->createMiddleware($decoratedMiddleware);
        $middleware->enableRequestTimeout($loop, 5.0);
        $middleware->onOpen($connection);

        $this->assertIsCallable($timerCallback);

        $timerCallback();

        $this->assertCount(1, $connection->sent);
        $this->assertStringStartsWith('HTTP/1.1 408 ', $connection->sent[0]);
        $this->assertSame(1, $connection->closeCount);
        $this->assertFalse($connection->getAttributeStore()->has('http.buffer'), 'The partial request buffer should be cleared.');
    }

    #[TestDox('Cancels the request timeout once the request has been received')]
    public function testRequestTimeoutIsCanceledWhenRequestParsed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        /** @var MockObject&RequestParser $requestParser */
        $requestParser = $this->createMock(RequestParser::class);

        $timer = $this->createStub(TimerInterface::class);

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addTimer')
            ->willReturn($timer);

        $loop->expects($this->once())
            ->method('cancelTimer')
            ->with($timer);

        $connection = new RecordingConnection();

        $requestParser->expects($this->once())
            ->method('parse')
            ->willReturn($this->createStub(RequestInterface::class));

        $decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        $middleware = $this->createMiddleware($decoratedMiddleware, $requestParser);
        $middleware->enableRequestTimeout($loop);
        $middleware->onOpen($connection);
        $middleware->onMessage($connection, "GET / HTTP/1.1\r\n\r\n");
    }

    #[TestDox('Cancels the request timeout when the connection is closed before the request is received')]
    public function testRequestTimeoutIsCanceledWhenConnectionClosed(): void
    {
        /** @var MockObject&ServerMiddleware $decoratedMiddleware */
        $decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $timer = $this->createStub(TimerInterface::class);

        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addTimer')
            ->willReturn($timer);

        $loop->expects($this->once())
            ->method('cancelTimer')
            ->with($timer);

        $decoratedMiddleware->expects($this->never())
            ->method('onClose');

        $connection = new RecordingConnection();

        $middleware = $this->createMiddleware($decoratedMiddleware);
        $middleware->enableRequestTimeout($loop);
        $middleware->onOpen($connection);
        $middleware->onClose($connection);
    }

    /**
     * @return \Generator<string, array{float}>
     */
    public static function dataInvalidRequestTimeout(): \Generator
    {
        yield 'Zero' => [0.0];

        yield 'Negative' => [-1.0];
    }

    #[TestDox('Rejects a request timeout which is not a positive number')]
    #[DataProvider('dataInvalidRequestTimeout')]
    public function testRequestTimeoutMustBePositive(float $timeout): void
    {
        $this->expectException(InvalidRequestTimeout::class);

        $this->createMiddleware()->enableRequestTimeout($this->createStub(LoopInterface::class), $timeout);
    }

    private function createMiddleware(?ServerMiddleware $decoratedMiddleware = null, ?RequestParser $requestParser = null): ParseHttpRequest
    {
        return new ParseHttpRequest(
            $decoratedMiddleware ?? $this->createStub(ServerMiddleware::class),
            $requestParser ?? $this->createStub(RequestParser::class),
        );
    }
}
