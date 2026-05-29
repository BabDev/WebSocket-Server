<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\Event\ConnectionClosed;
use BabDev\WebSocket\Server\Connection\Event\ConnectionError;
use BabDev\WebSocket\Server\Connection\Event\ConnectionOpened;
use BabDev\WebSocket\Server\RPCMessageHandler;
use BabDev\WebSocket\Server\TopicMessageHandler;
use BabDev\WebSocket\Server\WAMP\Exception\RouteNotFound;
use BabDev\WebSocket\Server\WAMP\Exception\UnknownMessageHandler;
use BabDev\WebSocket\Server\WAMP\MessageHandler\MessageHandlerResolver;
use BabDev\WebSocket\Server\WAMP\Middleware\DispatchMessageToHandler;
use BabDev\WebSocket\Server\WAMP\Topic;
use BabDev\WebSocket\Server\WAMP\WAMPConnection;
use BabDev\WebSocket\Server\WAMP\WAMPMessageRequest;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

final class DispatchMessageToHandlerTest extends TestCase
{
    public function testGetSubProtocols(): void
    {
        $this->assertEmpty($this->createMiddleware()->getSubProtocols());
    }

    #[TestDox('Handles a new connection being opened')]
    public function testOnOpen(): void
    {
        /** @var MockObject&EventDispatcherInterface $dispatcher */
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConnectionOpened::class));

        $this->createMiddleware(dispatcher: $dispatcher)->onOpen($this->createStub(Connection::class));
    }

    #[TestDox('Closes the connection')]
    public function testOnClose(): void
    {
        /** @var MockObject&EventDispatcherInterface $dispatcher */
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConnectionClosed::class));

        $this->createMiddleware(dispatcher: $dispatcher)->onClose($this->createStub(Connection::class));
    }

    #[TestDox('Handles an error')]
    public function testOnError(): void
    {
        /** @var MockObject&EventDispatcherInterface $dispatcher */
        $dispatcher = $this->createMock(EventDispatcherInterface::class);
        $dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConnectionError::class));

        $this->createMiddleware(dispatcher: $dispatcher)->onError($this->createStub(Connection::class), new \RuntimeException('Testing'));
    }

    #[TestDox('Handles an RPC "CALL" WAMP message')]
    public function testOnCall(): void
    {
        $id = uniqid();
        $resolvedUri = '/testing';
        $params = ['foo' => 'bar'];

        /** @var Stub&WAMPConnection $connection */
        $connection = $this->createStub(WAMPConnection::class);

        /** @var MockObject&RPCMessageHandler $handler */
        $handler = $this->createMock(RPCMessageHandler::class);
        $handler->expects($this->once())
            ->method('onCall')
            ->with($connection, $id, $this->isInstanceOf(WAMPMessageRequest::class), $params);

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($resolvedUri)
            ->willReturn(['_controller' => 'rpc.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onCall($connection, $id, $resolvedUri, $params);
    }

    #[TestDox('Handles an RPC "CALL" WAMP message when there is no handler for a URI')]
    public function testOnCallWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $id = uniqid();
        $resolvedUri = '/testing';
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('callError');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($resolvedUri)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onCall($connection, $id, $resolvedUri, $params);
    }

    #[TestDox('Handles an RPC "CALL" WAMP message when the handler is invalid')]
    public function testOnCallWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $id = uniqid();
        $resolvedUri = '/testing';
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('callError');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($resolvedUri)
            ->willReturn(['_controller' => 'rpc.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onCall($connection, $id, $resolvedUri, $params);
    }

    #[TestDox('Handles a "SUBSCRIBE" WAMP message')]
    public function testOnSubscribe(): void
    {
        $topic = new Topic('testing');

        /** @var Stub&WAMPConnection $connection */
        $connection = $this->createStub(WAMPConnection::class);

        /** @var MockObject&TopicMessageHandler $handler */
        $handler = $this->createMock(TopicMessageHandler::class);
        $handler->expects($this->once())
            ->method('onSubscribe')
            ->with($connection, $topic, $this->isInstanceOf(WAMPMessageRequest::class));

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onSubscribe($connection, $topic);
    }

    #[TestDox('Handles a "SUBSCRIBE" WAMP message when there is no handler for a URI')]
    public function testOnSubscribeWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onSubscribe($connection, $topic);
    }

    #[TestDox('Handles a "SUBSCRIBE" WAMP message when the handler is invalid')]
    public function testOnSubscribeWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'rpc.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onSubscribe($connection, $topic);
    }

    #[TestDox('Handles an "UNSUBSCRIBE" WAMP message')]
    public function testOnUnsubscribe(): void
    {
        $topic = new Topic('testing');

        /** @var Stub&WAMPConnection $connection */
        $connection = $this->createStub(WAMPConnection::class);

        /** @var MockObject&TopicMessageHandler $handler */
        $handler = $this->createMock(TopicMessageHandler::class);
        $handler->expects($this->once())
            ->method('onUnsubscribe')
            ->with($connection, $topic, $this->isInstanceOf(WAMPMessageRequest::class));

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onUnsubscribe($connection, $topic);
    }

    #[TestDox('Handles an "UNSUBSCRIBE" WAMP message when there is no handler for a URI')]
    public function testOnUnsubscribeWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onUnsubscribe($connection, $topic);
    }

    #[TestDox('Handles an "UNSUBSCRIBE" WAMP message when the handler is invalid')]
    public function testOnUnSubscribeWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'rpc.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onUnsubscribe($connection, $topic);
    }

    #[TestDox('Handles a "PUBLISH" WAMP message')]
    public function testOnPublish(): void
    {
        $topic = new Topic('testing');
        $event = ['foo' => 'bar'];
        $exclude = [];
        $eligible = [];

        /** @var Stub&WAMPConnection $connection */
        $connection = $this->createStub(WAMPConnection::class);

        /** @var MockObject&TopicMessageHandler $handler */
        $handler = $this->createMock(TopicMessageHandler::class);
        $handler->expects($this->once())
            ->method('onPublish')
            ->with($connection, $topic, $this->isInstanceOf(WAMPMessageRequest::class), $event, $exclude, $eligible);

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onPublish($connection, $topic, $event, $exclude, $eligible);
    }

    #[TestDox('Handles a "PUBLISH" WAMP message when there is no handler for a URI')]
    public function testOnPublishWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $topic = new Topic('testing');
        $event = ['foo' => 'bar'];
        $exclude = [];
        $eligible = [];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onPublish($connection, $topic, $event, $exclude, $eligible);
    }

    #[TestDox('Handles a "PUBLISH" WAMP message when the handler is invalid')]
    public function testOnPublishWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $topic = new Topic('testing');
        $event = ['foo' => 'bar'];
        $exclude = [];
        $eligible = [];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        /** @var MockObject&UrlMatcherInterface $matcher */
        $matcher = $this->createMock(UrlMatcherInterface::class);
        $matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        /** @var MockObject&MessageHandlerResolver $resolver */
        $resolver = $this->createMock(MessageHandlerResolver::class);
        $resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->createMiddleware(matcher: $matcher, resolver: $resolver)->onPublish($connection, $topic, $event, $exclude, $eligible);
    }

    private function createMiddleware(?UrlMatcherInterface $matcher = null, ?MessageHandlerResolver $resolver = null, ?EventDispatcherInterface $dispatcher = null): DispatchMessageToHandler
    {
        return new DispatchMessageToHandler(
            $matcher ?? $this->createStub(UrlMatcherInterface::class),
            $resolver ?? $this->createStub(MessageHandlerResolver::class),
            $dispatcher ?? $this->createStub(EventDispatcherInterface::class),
        );
    }
}
