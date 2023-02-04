<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Http;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use BabDev\WebSocket\Server\Http\GuzzleRequestParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\RequestInterface;

final class GuzzleRequestParserTest extends TestCase
{
    /**
     * @return \Generator<string, array>
     */
    public function dataRequestProvider(): \Generator
    {
        yield 'Invalid when the end of message marker is missing' => [false, "GET / HTTP/1.1\r\nHost: example.com\r\n"];
        yield 'Valid when the end of message marker is present' => [true, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n"];
        yield 'Valid when the end of message marker is present and a body exists after' => [true, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\n1"];
        yield 'Valid when the end of message marker is present and a body exists with UTF-8 content' => [true, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\nHixie✖"];
        yield 'Valid when the end of message marker is present and a body exists with UTF-8 content with end of message after the body' => [true, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\nHixie✖\r\n\r\n"];
        yield 'Valid when the end of message marker is present and a body exists with line breaks after the body' => [true, "GET / HTTP/1.1\r\nHost: example.com\r\n\r\nHixie\r\n"];
    }

    #[DataProvider('dataRequestProvider')]
    public function testConvertsToRequest(bool $valid, string $message): void
    {
        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.buffer', '')
            ->willReturn('');

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('http.buffer', $message);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        if ($valid) {
            $attributeStore->expects($this->once())
                ->method('remove')
                ->with('http.buffer');
            $this->assertInstanceOf(
                RequestInterface::class,
                (new GuzzleRequestParser())->parse($connection, $message)
            );
        } else {
            $this->assertNull((new GuzzleRequestParser())->parse($connection, $message));
        }
    }

    public function testRejectsARequestWhichIsTooBig(): void
    {
        $this->expectException(\OverflowException::class);

        $message = 'Header-Is: Too Big';

        /** @var MockObject&AttributeStore $attributeStore */
        $attributeStore = $this->createMock(AttributeStore::class);
        $attributeStore->expects($this->once())
            ->method('get')
            ->with('http.buffer', '')
            ->willReturn('');

        $attributeStore->expects($this->once())
            ->method('set')
            ->with('http.buffer', $message);

        /** @var MockObject&Connection $connection */
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->atLeastOnce())
            ->method('getAttributeStore')
            ->willReturn($attributeStore);

        $parser = new GuzzleRequestParser();
        $parser->maxRequestSize = 10;

        $parser->parse($connection, $message);
    }
}
