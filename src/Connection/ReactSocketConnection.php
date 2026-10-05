<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

use BabDev\WebSocket\Server\Connection;
use React\Socket\ConnectionInterface;

/**
 * The React socket connection is a connection class wrapping a {@see ConnectionInterface}
 * from the `react/socket` package.
 */
final class ReactSocketConnection implements Connection
{
    private bool $writeBufferFull = false;

    /**
     * @var int<0, max>
     */
    private int $bytesWrittenWhileFull = 0;

    /**
     * @param int<1, max>|null $writeBufferLimit
     */
    public function __construct(
        private readonly ConnectionInterface $connection,
        private readonly AttributeStore $attributeStore,
        private readonly ?int $writeBufferLimit = null,
    ) {
        if (null !== $writeBufferLimit) {
            $connection->on('drain', function (): void {
                $this->writeBufferFull = false;
                $this->bytesWrittenWhileFull = 0;
            });
        }
    }

    public function getAttributeStore(): AttributeStore
    {
        return $this->attributeStore;
    }

    public function getConnection(): ConnectionInterface
    {
        return $this->connection;
    }

    public function send(string $data): void
    {
        if (null !== $this->writeBufferLimit && $this->writeBufferFull) {
            $this->bytesWrittenWhileFull += \strlen($data);

            if ($this->bytesWrittenWhileFull > $this->writeBufferLimit) {
                // The client is not reading the data sent to it, so drop the connection without flushing the buffer
                $this->connection->close();

                return;
            }
        }

        if (!$this->connection->write($data)) {
            $this->writeBufferFull = true;
        }
    }

    public function close(mixed $data = null): void
    {
        $this->connection->end($data);
    }
}
