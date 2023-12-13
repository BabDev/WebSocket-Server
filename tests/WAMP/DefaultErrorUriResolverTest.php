<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\WAMP;

use BabDev\WebSocket\Server\WAMP\DefaultErrorUriResolver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class DefaultErrorUriResolverTest extends TestCase
{
    /**
     * @return \Generator<string, array{non-empty-string, non-empty-string}>
     */
    public static function dataSupportedErrors(): \Generator
    {
        yield '"not-found" error' => ['not-found', 'https://example.com/error#not-found'];

        yield 'Unknown error' => ['unknown', 'https://example.com/error#generic'];
    }

    /**
     * @param non-empty-string $errorType
     * @param non-empty-string $expected
     */
    #[DataProvider('dataSupportedErrors')]
    public function testResolve(string $errorType, string $expected): void
    {
        $this->assertSame($expected, (new DefaultErrorUriResolver())->resolve($errorType));
    }
}
