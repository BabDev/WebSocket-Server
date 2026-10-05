<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;
use BabDev\WebSocket\Server\Session\Reader\PhpBinaryReader;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class PhpBinaryReaderTest extends TestCase
{
    public function testReadsData(): void
    {
        $input = '_sf2_attributesa:3:{s:3:"foo";s:3:"bar";s:12:"messages.foo";s:3:"bar";s:4:"data";a:1:{s:3:"foo";s:3:"bar";}}	_sf2_metaa:3:{s:1:"u";i:1653958488;s:1:"c";i:1653958488;s:1:"l";i:0;}';

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

        $this->assertSame($expected, new PhpBinaryReader()->read($input));
    }

    #[TestDox('Reads data with multiple variables without emitting warnings')]
    public function testReadsMultipleVariablesWithoutWarnings(): void
    {
        $input = \chr(3).'foo'.serialize('bar').\chr(4).'null'.serialize(null).\chr(5).'false'.serialize(false);

        $warnings = [];

        set_error_handler(static function (int $errno, string $errstr) use (&$warnings): bool {
            $warnings[] = $errstr;

            return true;
        });

        try {
            $output = new PhpBinaryReader()->read($input);
        } finally {
            restore_error_handler();
        }

        $this->assertSame(['foo' => 'bar', 'null' => null, 'false' => false], $output);
        $this->assertSame([], $warnings);
    }

    /**
     * @return \Generator<string, array{string}>
     */
    public static function dataInvalidData(): \Generator
    {
        yield 'Name longer than the data' => [\chr(20).'foo'];

        yield 'Missing value' => [\chr(3).'foo'];

        yield 'Truncated value' => [\chr(3).'foos:5:"hel'];

        yield 'Reference' => [\chr(3).'fooR:1;'];
    }

    #[TestDox('Throws an exception when the session data is invalid')]
    #[DataProvider('dataInvalidData')]
    public function testRejectsInvalidData(string $input): void
    {
        $this->expectException(InvalidSession::class);

        new PhpBinaryReader()->read($input);
    }
}
