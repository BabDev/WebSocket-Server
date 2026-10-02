<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Tests\Fixtures;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\ArrayAttributeStore;
use BabDev\WebSocket\Server\Connection\AttributeStore;

/**
 * A connection which records the data sent to it and whether it has been closed.
 */
final class RecordingConnection implements Connection
{
    /**
     * @var list<string>
     */
    public array $sent = [];

    public int $closeCount = 0;

    private readonly AttributeStore $attributeStore;

    public function __construct()
    {
        $this->attributeStore = new ArrayAttributeStore();
    }

    public function getAttributeStore(): AttributeStore
    {
        return $this->attributeStore;
    }

    public function getConnection(): mixed
    {
        return null;
    }

    public function send(string $data): void
    {
        $this->sent[] = $data;
    }

    public function close(mixed $data = null): void
    {
        ++$this->closeCount;
    }
}
