<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http\Middleware;

use BabDev\WebSocket\Server\Http\Exception\ConflictingForwardedHeaders;
use BabDev\WebSocket\Server\Http\Exception\MissingRequest;
use BabDev\WebSocket\Server\Http\Middleware\ResolveForwardedClientAddress;
use BabDev\WebSocket\Server\ServerMiddleware;
use BabDev\WebSocket\Server\Tests\Fixtures\RecordingConnection;
use GuzzleHttp\Psr7\Request as Psr7Request;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Exception\ConflictingHeadersException;
use Symfony\Component\HttpFoundation\Request;

final class ResolveForwardedClientAddressTest extends TestCase
{
    private readonly MockObject&ServerMiddleware $decoratedMiddleware;

    /**
     * @var array<string>
     */
    private array $symfonyTrustedProxies;

    /**
     * @var int-mask-of<Request::HEADER_*>
     */
    private int $symfonyTrustedHeaderSet;

    protected function setUp(): void
    {
        $this->decoratedMiddleware = $this->createMock(ServerMiddleware::class);

        $this->symfonyTrustedProxies = Request::getTrustedProxies();
        /** @var int-mask-of<Request::HEADER_*> $trustedHeaderSet */
        $trustedHeaderSet = Request::getTrustedHeaderSet();

        $this->symfonyTrustedHeaderSet = $trustedHeaderSet;
    }

    protected function tearDown(): void
    {
        Request::setTrustedProxies($this->symfonyTrustedProxies, $this->symfonyTrustedHeaderSet);
    }

    /**
     * @return \Generator<string, array{list<string>, int, non-empty-string, array<string, string>, non-empty-string, non-empty-string|null, bool}>
     */
    public static function dataClientAddress(): \Generator
    {
        $xff = Request::HEADER_X_FORWARDED_FOR;
        $forwarded = Request::HEADER_FORWARDED;

        yield 'Untrusted connection ignores the header' => [['10.0.0.1'], $xff, '203.0.113.99', ['X-Forwarded-For' => '198.51.100.1'], '203.0.113.99', null, true];

        yield 'Trusted connection without a forwarding header' => [['10.0.0.1'], $xff, '10.0.0.1', [], '10.0.0.1', null, true];

        yield 'Trusted connection with a single address' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.5'], '203.0.113.5', '10.0.0.1', true];

        yield 'Spoofed address prepended by the client is ignored' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '198.51.100.1, 203.0.113.5'], '203.0.113.5', '10.0.0.1', true];

        yield 'Chain of trusted proxies' => [['10.0.0.0/8'], $xff, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.5, 10.0.0.2'], '203.0.113.5', '10.0.0.1', true];

        yield 'Chain of only trusted proxies uses the first trusted address' => [['10.0.0.0/8'], $xff, '10.0.0.1', ['X-Forwarded-For' => '10.0.0.5, 10.0.0.2'], '10.0.0.5', '10.0.0.1', true];

        yield 'Invalid addresses are ignored' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.5, not-an-address'], '203.0.113.5', '10.0.0.1', true];

        yield 'IPv4 address with a port' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.5:4711'], '203.0.113.5', '10.0.0.1', true];

        yield 'IPv6 address with brackets and a port' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '[2001:db8::1]:4711'], '2001:db8::1', '10.0.0.1', true];

        yield 'IPv6 address' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '2001:db8::1'], '2001:db8::1', '10.0.0.1', true];

        // Symfony does not normalize IPv4-mapped addresses, this package does for consistency with the remote address
        yield 'IPv4-mapped IPv6 address is normalized' => [['10.0.0.1'], $xff, '10.0.0.1', ['X-Forwarded-For' => '::ffff:203.0.113.5'], '203.0.113.5', '10.0.0.1', false];

        yield 'Untrusted header type is ignored' => [['10.0.0.1'], $forwarded, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.5'], '10.0.0.1', null, true];

        yield 'Forwarded header' => [['10.0.0.1'], $forwarded, '10.0.0.1', ['Forwarded' => 'for=203.0.113.5'], '203.0.113.5', '10.0.0.1', true];

        yield 'Forwarded header with a quoted IPv6 address and other parameters' => [['10.0.0.1'], $forwarded, '10.0.0.1', ['Forwarded' => 'for="[2001:db8::1]:4711";proto=https'], '2001:db8::1', '10.0.0.1', true];

        yield 'Forwarded header with multiple elements' => [['10.0.0.1'], $forwarded, '10.0.0.1', ['Forwarded' => 'for=198.51.100.1, for=203.0.113.5'], '203.0.113.5', '10.0.0.1', true];

        yield 'Both headers trusted and in agreement' => [['10.0.0.1'], $xff | $forwarded, '10.0.0.1', ['X-Forwarded-For' => '203.0.113.5', 'Forwarded' => 'for=203.0.113.5'], '203.0.113.5', '10.0.0.1', true];

        yield 'Both headers trusted with only one present' => [['10.0.0.1'], $xff | $forwarded, '10.0.0.1', ['Forwarded' => 'for=203.0.113.5'], '203.0.113.5', '10.0.0.1', true];

        yield 'Private subnets keyword' => [['PRIVATE_SUBNETS'], $xff, '192.168.1.10', ['X-Forwarded-For' => '203.0.113.5'], '203.0.113.5', '192.168.1.10', true];

        yield 'Remote address keyword trusts any connection' => [['REMOTE_ADDR'], $xff, '203.0.113.99', ['X-Forwarded-For' => '198.51.100.1'], '198.51.100.1', '203.0.113.99', true];
    }

    /**
     * @param list<string>                   $trustedProxies
     * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet
     * @param non-empty-string               $remoteAddress
     * @param array<string, string>          $headers
     * @param non-empty-string               $expectedRemoteAddress
     * @param non-empty-string|null          $expectedProxyAddress
     */
    #[TestDox('Resolves the client address from the trusted forwarding headers')]
    #[DataProvider('dataClientAddress')]
    public function testResolvesClientAddress(array $trustedProxies, int $trustedHeaderSet, string $remoteAddress, array $headers, string $expectedRemoteAddress, ?string $expectedProxyAddress, bool $matchesSymfony): void
    {
        $connection = $this->createConnection($remoteAddress, $headers);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        new ResolveForwardedClientAddress($this->decoratedMiddleware, $trustedProxies, $trustedHeaderSet)->onOpen($connection);

        $this->assertSame($expectedRemoteAddress, $connection->getAttributeStore()->get('remote_address'));
        $this->assertSame($expectedProxyAddress, $connection->getAttributeStore()->get('proxy_address'));

        if ($matchesSymfony) {
            $this->assertSame($this->getSymfonyClientIp($trustedProxies, $trustedHeaderSet, $remoteAddress, $headers), $expectedRemoteAddress, 'The client address should match the address resolved by Symfony\'s Request class.');
        }
    }

    #[TestDox('Rejects a request with conflicting trusted forwarding headers')]
    public function testRejectsConflictingHeaders(): void
    {
        $headers = ['X-Forwarded-For' => '198.51.100.1', 'Forwarded' => 'for=203.0.113.5'];

        $connection = $this->createConnection('10.0.0.1', $headers);

        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        try {
            new ResolveForwardedClientAddress($this->decoratedMiddleware, ['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED)->onOpen($connection);

            self::fail(\sprintf('A %s exception should have been thrown.', ConflictingForwardedHeaders::class));
        } catch (ConflictingForwardedHeaders) {
            // Expected
        }

        $this->assertStringStartsWith('HTTP/1.1 400 ', $connection->sent[0] ?? '');
        $this->assertSame(1, $connection->closeCount);
        $this->assertSame('10.0.0.1', $connection->getAttributeStore()->get('remote_address'));

        try {
            $this->getSymfonyClientIp(['10.0.0.1'], Request::HEADER_X_FORWARDED_FOR | Request::HEADER_FORWARDED, '10.0.0.1', $headers);

            self::fail('Symfony\'s Request class should also reject the conflicting headers.');
        } catch (ConflictingHeadersException) {
            // Expected
        }
    }

    #[TestDox('Does not change a connection without a remote address')]
    public function testConnectionWithoutRemoteAddress(): void
    {
        $connection = new RecordingConnection();
        $connection->getAttributeStore()->set('http.request', new Psr7Request('GET', '/', ['X-Forwarded-For' => '203.0.113.5']));

        $this->decoratedMiddleware->expects($this->once())
            ->method('onOpen')
            ->with($connection);

        new ResolveForwardedClientAddress($this->decoratedMiddleware, ['REMOTE_ADDR'])->onOpen($connection);

        $this->assertFalse($connection->getAttributeStore()->has('remote_address'));
        $this->assertFalse($connection->getAttributeStore()->has('proxy_address'));
    }

    #[TestDox('Handles a new connection being opened when required middleware have not run before this middleware')]
    public function testOnOpenWithoutRequest(): void
    {
        $this->decoratedMiddleware->expects($this->never())
            ->method('onOpen');

        $this->expectException(MissingRequest::class);

        new ResolveForwardedClientAddress($this->decoratedMiddleware, ['10.0.0.1'])->onOpen(new RecordingConnection());
    }

    #[TestDox('Forwards incoming data, closed connections, and errors to the decorated middleware')]
    public function testForwardsOtherEvents(): void
    {
        $connection = new RecordingConnection();
        $exception = new \RuntimeException('Testing');

        $this->decoratedMiddleware->expects($this->once())
            ->method('onMessage')
            ->with($connection, 'Testing');

        $this->decoratedMiddleware->expects($this->once())
            ->method('onClose')
            ->with($connection);

        $this->decoratedMiddleware->expects($this->once())
            ->method('onError')
            ->with($connection, $exception);

        $middleware = new ResolveForwardedClientAddress($this->decoratedMiddleware, ['10.0.0.1']);
        $middleware->onMessage($connection, 'Testing');
        $middleware->onClose($connection);
        $middleware->onError($connection, $exception);
    }

    /**
     * @param non-empty-string      $remoteAddress
     * @param array<string, string> $headers
     */
    private function createConnection(string $remoteAddress, array $headers): RecordingConnection
    {
        $connection = new RecordingConnection();
        $connection->getAttributeStore()->set('remote_address', $remoteAddress);
        $connection->getAttributeStore()->set('http.request', new Psr7Request('GET', '/', $headers));

        return $connection;
    }

    /**
     * @param list<string>                   $trustedProxies
     * @param int-mask-of<Request::HEADER_*> $trustedHeaderSet
     * @param array<string, string>          $headers
     */
    private function getSymfonyClientIp(array $trustedProxies, int $trustedHeaderSet, string $remoteAddress, array $headers): ?string
    {
        $server = ['REMOTE_ADDR' => $remoteAddress];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $value;
        }

        // Symfony replaces the "REMOTE_ADDR" keyword using the superglobal
        $previousRemoteAddress = $_SERVER['REMOTE_ADDR'] ?? null;
        $_SERVER['REMOTE_ADDR'] = $remoteAddress;

        try {
            Request::setTrustedProxies($trustedProxies, $trustedHeaderSet);

            return Request::create('/', server: $server)->getClientIp();
        } finally {
            if (null === $previousRemoteAddress) {
                unset($_SERVER['REMOTE_ADDR']);
            } else {
                $_SERVER['REMOTE_ADDR'] = $previousRemoteAddress;
            }
        }
    }
}
