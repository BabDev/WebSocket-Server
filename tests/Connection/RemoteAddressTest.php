<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Connection;

use BabDev\WebSocket\Server\Connection\RemoteAddress;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class RemoteAddressTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, non-empty-string|null}>
     */
    public static function dataRemoteAddress(): \Generator
    {
        yield 'IPv4 address with scheme' => ['tcp://203.0.113.5:8080', '203.0.113.5'];

        yield 'IPv4 address without scheme' => ['203.0.113.5:8080', '203.0.113.5'];

        yield 'IPv4 address over TLS' => ['tls://203.0.113.5:443', '203.0.113.5'];

        yield 'IPv6 loopback address' => ['tcp://[::1]:8080', '::1'];

        yield 'IPv6 address without scheme' => ['[2001:db8::1]:8080', '2001:db8::1'];

        yield 'IPv6 address over TLS' => ['tls://[2001:db8::1]:443', '2001:db8::1'];

        yield 'IPv6 link-local address with zone ID' => ['tcp://[fe80::1%en0]:8080', 'fe80::1'];

        yield 'IPv4-mapped IPv6 address' => ['tcp://[::ffff:203.0.113.5]:8080', '203.0.113.5'];

        yield 'IPv4-mapped IPv6 address in hexadecimal notation' => ['tcp://[::ffff:cb00:7105]:8080', '203.0.113.5'];

        yield 'IPv4-compatible IPv6 address is not converted' => ['tcp://[::203.0.113.5]:8080', '::203.0.113.5'];

        yield 'Unix socket' => ['unix:///tmp/websocket.sock', null];

        yield 'Hostname' => ['tcp://localhost:8080', null];

        yield 'Empty string' => ['', null];
    }

    /**
     * @param non-empty-string|null $expected
     */
    #[TestDox('Extracts the normalized IP address from a remote address URI')]
    #[DataProvider('dataRemoteAddress')]
    public function testFromUri(string $uri, ?string $expected): void
    {
        $this->assertSame($expected, RemoteAddress::fromUri($uri));
    }
}
