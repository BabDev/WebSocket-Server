<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Session\Reader;

use BabDev\WebSocket\Server\Session\Exception\InvalidSession;
use BabDev\WebSocket\Server\Session\Reader\SerializedValue;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\TestCase;

final class SerializedValueTest extends TestCase
{
    /**
     * @return \Generator<string, array{string, int|false}>
     */
    public static function dataLength(): \Generator
    {
        yield 'Null' => ['N;', 2];

        yield 'Boolean' => ['b:1;', 4];

        yield 'Integer' => ['i:-42;', 6];

        yield 'Float' => ['d:4.2;', 6];

        yield 'String' => ['s:5:"hello";', 12];

        yield 'String containing serialization syntax' => ['s:7:"a";i:1;";', 14];

        yield 'Multibyte string' => [serialize('✓'), \strlen(serialize('✓'))];

        yield 'Enum' => ['E:58:"BabDev\WebSocket\Server\Tests\Session\Reader\Status:Active";', 66];

        yield 'Array' => [$array = serialize(['foo' => 'bar', 'nested' => [1, 2, null]]), \strlen($array)];

        yield 'Object' => [$object = serialize(new \ArrayObject([1, 2])), \strlen($object)];

        yield 'Object using the Serializable interface' => ['C:3:"Foo":3:{abc}', 17];

        yield 'Value followed by more data' => ['i:1;s:3:"foo";', 4];

        yield 'Empty data' => ['', false];

        yield 'Truncated string' => ['s:5:"hel', false];

        yield 'String with an incorrect length' => ['s:3:"hello";', false];

        yield 'Truncated array' => ['a:2:{i:0;i:1;', false];

        yield 'Reference' => ['R:1;', false];

        yield 'Unknown type' => ['x:1;', false];
    }

    #[TestDox('Gets the length of the serialized value at the start of the data')]
    #[DataProvider('dataLength')]
    public function testGetLength(string $data, int|false $expected): void
    {
        $this->assertSame($expected, SerializedValue::getLength($data));
    }

    #[TestDox('Deserializes a serialized false value')]
    public function testUnserializeFalse(): void
    {
        $this->assertFalse(SerializedValue::unserialize('b:0;', 'b:0;'));
    }

    #[TestDox('Throws an exception when the value cannot be deserialized')]
    public function testUnserializeInvalidValue(): void
    {
        try {
            SerializedValue::unserialize('s:5:"hel', 'session-data');

            self::fail(\sprintf('A %s exception should have been thrown.', InvalidSession::class));
        } catch (InvalidSession $exception) {
            $this->assertSame('session-data', $exception->sessionData);
        }
    }

    #[TestDox('Restores the previous error handler after deserializing a value')]
    public function testRestoresErrorHandler(): void
    {
        $handler = static fn (): bool => false;

        set_error_handler($handler);

        try {
            try {
                SerializedValue::unserialize('s:5:"hel', 'session-data');
            } catch (InvalidSession) {
                // Expected
            }

            SerializedValue::unserialize('i:1;', 'i:1;');

            $this->assertSame($handler, set_error_handler(null));
        } finally {
            restore_error_handler();
            restore_error_handler();
        }
    }
}
