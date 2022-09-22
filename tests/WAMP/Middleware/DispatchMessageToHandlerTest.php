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
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

final class DispatchMessageToHandlerTest extends TestCase
{
    private MockObject & UrlMatcherInterface $matcher;

    private MockObject & MessageHandlerResolver $resolver;

    private MockObject & EventDispatcherInterface $dispatcher;

    private DispatchMessageToHandler $middleware;

    protected function setUp(): void
    {
        $this->matcher = $this->createMock(UrlMatcherInterface::class);
        $this->resolver = $this->createMock(MessageHandlerResolver::class);
        $this->dispatcher = $this->createMock(EventDispatcherInterface::class);

        $this->middleware = new DispatchMessageToHandler($this->matcher, $this->resolver, $this->dispatcher);
    }

    public function testGetSubProtocols(): void
    {
        $this->assertSame(
            [],
            $this->middleware->getSubProtocols(),
        );
    }

    /**
     * @testdox Handles a new connection being opened
     */
    public function testOnOpen(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConnectionOpened::class));

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Closes the connection
     */
    public function testOnClose(): void
    {
        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConnectionClosed::class));

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->middleware->onClose($connection);
    }

    /**
     * @testdox Handles an error
     */
    public function testOnError(): void
    {
        $exception = new \RuntimeException('Testing');

        $this->dispatcher->expects($this->once())
            ->method('dispatch')
            ->with($this->isInstanceOf(ConnectionError::class));

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);

        $this->middleware->onError($connection, $exception);
    }

    /**
     * @testdox Handles an RPC "CALL" WAMP message
     */
    public function testOnCall(): void
    {
        $id = uniqid();
        $resolvedUri = '/testing';
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        /** @var MockObject&RPCMessageHandler $handler */
        $handler = $this->createMock(RPCMessageHandler::class);
        $handler->expects($this->once())
            ->method('onCall')
            ->with($connection, $id, $this->isInstanceOf(WAMPMessageRequest::class), $params);

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($resolvedUri)
            ->willReturn(['_controller' => 'rpc.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->middleware->onCall($connection, $id, $resolvedUri, $params);
    }

    /**
     * @testdox Handles an RPC "CALL" WAMP message when there is no handler for a URI
     */
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($resolvedUri)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        $this->resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->middleware->onCall($connection, $id, $resolvedUri, $params);
    }

    /**
     * @testdox Handles an RPC "CALL" WAMP message when the handler is invalid
     */
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($resolvedUri)
            ->willReturn(['_controller' => 'rpc.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->middleware->onCall($connection, $id, $resolvedUri, $params);
    }

    /**
     * @testdox Handles a "SUBSCRIBE" WAMP message
     */
    public function testOnSubscribe(): void
    {
        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        /** @var MockObject&TopicMessageHandler $handler */
        $handler = $this->createMock(TopicMessageHandler::class);
        $handler->expects($this->once())
            ->method('onSubscribe')
            ->with($connection, $topic, $this->isInstanceOf(WAMPMessageRequest::class));

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->middleware->onSubscribe($connection, $topic);
    }

    /**
     * @testdox Handles a "SUBSCRIBE" WAMP message when there is no handler for a URI
     */
    public function testOnSubscribeWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        $this->resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->middleware->onSubscribe($connection, $topic);
    }

    /**
     * @testdox Handles a "SUBSCRIBE" WAMP message when the handler is invalid
     */
    public function testOnSubscribeWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'rpc.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->middleware->onSubscribe($connection, $topic);
    }

    /**
     * @testdox Handles an "UNSUBSCRIBE" WAMP message
     */
    public function testOnUnsubscribe(): void
    {
        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        /** @var MockObject&TopicMessageHandler $handler */
        $handler = $this->createMock(TopicMessageHandler::class);
        $handler->expects($this->once())
            ->method('onUnsubscribe')
            ->with($connection, $topic, $this->isInstanceOf(WAMPMessageRequest::class));

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->middleware->onUnsubscribe($connection, $topic);
    }

    /**
     * @testdox Handles an "UNSUBSCRIBE" WAMP message when there is no handler for a URI
     */
    public function testOnUnsubscribeWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        $this->resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->middleware->onUnsubscribe($connection, $topic);
    }

    /**
     * @testdox Handles an "UNSUBSCRIBE" WAMP message when the handler is invalid
     */
    public function testOnUnSubscribeWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $topic = new Topic('testing');

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('event');

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'rpc.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->middleware->onUnsubscribe($connection, $topic);
    }

    /**
     * @testdox Handles a "PUBLISH" WAMP message
     */
    public function testOnPublish(): void
    {
        $topic = new Topic('testing');
        $event = ['foo' => 'bar'];
        $exclude = [];
        $eligible = [];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        /** @var MockObject&TopicMessageHandler $handler */
        $handler = $this->createMock(TopicMessageHandler::class);
        $handler->expects($this->once())
            ->method('onPublish')
            ->with($connection, $topic, $this->isInstanceOf(WAMPMessageRequest::class), $event, $exclude, $eligible);

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->middleware->onPublish($connection, $topic, $event, $exclude, $eligible);
    }

    /**
     * @testdox Handles a "PUBLISH" WAMP message when there is no handler for a URI
     */
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        $this->resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->middleware->onPublish($connection, $topic, $event, $exclude, $eligible);
    }

    /**
     * @testdox Handles a "PUBLISH" WAMP message when the handler is invalid
     */
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

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'topic.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->middleware->onPublish($connection, $topic, $event, $exclude, $eligible);
    }
}
