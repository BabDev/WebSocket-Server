<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Reader;

use BabDev\WebSocket\Server\Session\Reader\PhpReader;
use PHPUnit\Framework\TestCase;

final class PhpReaderTest extends TestCase
{
    public function testReadsData(): void
    {
        $input = '_sf2_attributes|a:3:{s:3:"foo";s:3:"bar";s:12:"messages.foo";s:3:"bar";s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}_sf2_meta|a:3:{s:1:"u";i:1653958488;s:1:"c";i:1653958488;s:1:"l";i:0;}';

        $expected = [
            '_sf2_attributes' => [
                'foo' => 'bar',
                'messages.foo' => 'bar',
                'data' => ['foo' => 'bar'],
            ],
            '_sf2_meta' => [
                'u' => 1653958488,
                'c' => 1653958488,
                'l' => 0,
            ],
        ];

        $this->assertSame($expected, (new PhpReader())->read($input));
    }
}
