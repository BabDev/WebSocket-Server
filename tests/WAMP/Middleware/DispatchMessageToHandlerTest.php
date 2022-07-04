<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP\Middleware;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
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
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\UriInterface;
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;
use Symfony\Component\Routing\RequestContext;

final class DispatchMessageToHandlerTest extends TestCase
{
    private MockObject & UrlMatcherInterface $matcher;

    private MockObject & MessageHandlerResolver $resolver;

    private DispatchMessageToHandler $middleware;

    protected function setUp(): void
    {
        $this->matcher = $this->createMock(UrlMatcherInterface::class);
        $this->resolver = $this->createMock(MessageHandlerResolver::class);

        $this->middleware = new DispatchMessageToHandler($this->matcher, $this->resolver);
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
        /** @var MockObject&UriInterface $uri */
        $uri = $this->createMock(UriInterface::class);
        $uri->expects($this->once())
            ->method('getHost')
            ->willReturn('localhost');

        /** @var MockObject&RequestInterface $request */
        $request = $this->createMock(RequestInterface::class);
        $request->expects($this->once())
            ->method('getUri')
            ->willReturn($uri);

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.request')
            ->willReturn($request);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        /** @var MockObject&RequestContext $context */
        $context = $this->createMock(RequestContext::class);
        $context->expects($this->once())
            ->method('setHost')
            ->with('localhost')
            ->willReturnSelf();

        $this->matcher->expects($this->once())
            ->method('getContext')
            ->willReturn($context);

        $this->middleware->onOpen($connection);
    }

    /**
     * @testdox Handles an RPC "CALL" WAMP message
     */
    public function testOnCall(): void
    {
        $id = uniqid();
        $topic = new Topic('testing');
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);

        /** @var MockObject&RPCMessageHandler $handler */
        $handler = $this->createMock(RPCMessageHandler::class);
        $handler->expects($this->once())
            ->method('onCall')
            ->with($connection, $id, $topic, $this->isInstanceOf(WAMPMessageRequest::class), $params);

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'rpc.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willReturn($handler);

        $this->middleware->onCall($connection, $id, $topic, $params);
    }

    /**
     * @testdox Handles an RPC "CALL" WAMP message when there is no handler for a URI
     */
    public function testOnCallWithUndefinedHandler(): void
    {
        $this->expectException(RouteNotFound::class);

        $id = uniqid();
        $topic = new Topic('testing');
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('callError');

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willThrowException(new ResourceNotFoundException('Testing'));

        $this->resolver->expects($this->never())
            ->method('findMessageHandler');

        $this->middleware->onCall($connection, $id, $topic, $params);
    }

    /**
     * @testdox Handles an RPC "CALL" WAMP message when the handler is invalid
     */
    public function testOnCallWithInvalidHandler(): void
    {
        $this->expectException(UnknownMessageHandler::class);

        $id = uniqid();
        $topic = new Topic('testing');
        $params = ['foo' => 'bar'];

        /** @var MockObject&WAMPConnection $connection */
        $connection = $this->createMock(WAMPConnection::class);
        $connection->expects($this->once())
            ->method('callError');

        $this->matcher->expects($this->once())
            ->method('match')
            ->with($topic->id)
            ->willReturn(['_controller' => 'rpc.handler']);

        $this->resolver->expects($this->once())
            ->method('findMessageHandler')
            ->with($this->isInstanceOf(WAMPMessageRequest::class))
            ->willThrowException(new UnknownMessageHandler('Testing'));

        $this->middleware->onCall($connection, $id, $topic, $params);
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
