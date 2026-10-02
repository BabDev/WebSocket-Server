<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests;

use BabDev\WebSocket\Server\Application;
use PHPUnit\Framework\Attributes\TestDox;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use React\EventLoop\LoopInterface;
use React\EventLoop\StreamSelectLoop;
use React\EventLoop\TimerInterface;

final class ApplicationTest extends TestCase
{
    private string $socketPath;

    protected function setUp(): void
    {
        $this->socketPath = sys_get_temp_dir().'/babdev-websocket-'.bin2hex(random_bytes(4)).'.sock';
    }

    protected function tearDown(): void
    {
        if (file_exists($this->socketPath)) {
            unlink($this->socketPath);
        }
    }

    #[TestDox('Closes connections which do not send a request before the request timeout')]
    public function testRequestTimeoutClosesStalledConnection(): void
    {
        $loop = new StreamSelectLoop();

        $client = $this->runWithStalledClient(new Application("unix://{$this->socketPath}", [], $loop)->withRequestTimeout(0.05), $loop);

        $response = (string) stream_get_contents($client);

        $this->assertStringStartsWith('HTTP/1.1 408 ', $response);
        $this->assertTrue(feof($client), 'The connection should be closed by the server.');
    }

    #[TestDox('Keeps stalled connections open when the request timeout is disabled')]
    public function testRequestTimeoutCanBeDisabled(): void
    {
        $loop = new StreamSelectLoop();

        $client = $this->runWithStalledClient(new Application("unix://{$this->socketPath}", [], $loop)->withRequestTimeout(null), $loop);

        $this->assertSame('', stream_get_contents($client));
        $this->assertFalse(feof($client), 'The connection should remain open.');
    }

    #[TestDox('Rejects a request timeout which is not a positive number')]
    public function testRequestTimeoutMustBePositive(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Application("unix://{$this->socketPath}", [], $this->createStub(LoopInterface::class))->withRequestTimeout(0.0);
    }

    #[TestDox('Enables the keepalive when configured')]
    public function testKeepAliveIsEnabled(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->once())
            ->method('addPeriodicTimer')
            ->with(15, $this->isCallable())
            ->willReturn($this->createStub(TimerInterface::class));

        $loop->expects($this->once())
            ->method('run');

        new Application("unix://{$this->socketPath}", [], $loop)->withKeepAlive(15)->run();
    }

    #[TestDox('Does not enable the keepalive by default')]
    public function testKeepAliveIsDisabledByDefault(): void
    {
        /** @var MockObject&LoopInterface $loop */
        $loop = $this->createMock(LoopInterface::class);
        $loop->expects($this->never())
            ->method('addPeriodicTimer');

        $loop->expects($this->once())
            ->method('run');

        new Application("unix://{$this->socketPath}", [], $loop)->run();
    }

    /**
     * Runs the application long enough for a client which never sends a request to connect and be timed out.
     *
     * @return resource
     */
    private function runWithStalledClient(Application $application, LoopInterface $loop)
    {
        $client = null;

        $loop->addTimer(0.01, function () use (&$client): void {
            $client = stream_socket_client("unix://{$this->socketPath}");
        });

        $loop->addTimer(0.25, static function () use ($loop): void {
            $loop->stop();
        });

        $application->run();

        if (!\is_resource($client)) {
            self::fail('The client could not connect to the server.');
        }

        stream_set_blocking($client, false);

        return $client;
    }
}
