<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WebSocket;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Connection\AttributeKey;
use BabDev\WebSocket\Server\Connection\AttributeStore;
use Ratchet\RFC6455\Messaging\DataInterface;
use Ratchet\RFC6455\Messaging\Frame;

/**
 * The websocket connection is a connection class decorating another {@see Connection} adding support for
 * processing messages using the `ratchet/rfc6455` package.
 */
final readonly class DefaultWebSocketConnection implements WebSocketConnection
{
    public function __construct(private Connection $connection) {}

    public function getAttributeStore(): AttributeStore
    {
        return $this->connection->getAttributeStore();
    }

    public function getConnection(): Connection
    {
        return $this->connection;
    }

    public function send(string|DataInterface $data): void
    {
        if (false === $this->getAttributeStore()->get(AttributeKey::WEBSOCKET_CLOSING, false)) {
            if (!$data instanceof DataInterface) {
                $data = new Frame($data);
            }

            $this->connection->send($data->getContents());
        }
    }

    public function close(mixed $data = 1000): void
    {
        if (true === $this->getAttributeStore()->get(AttributeKey::WEBSOCKET_CLOSING, false)) {
            return;
        }

        if ($data instanceof DataInterface) {
            $this->send($data);
        } else {
            $this->send(new Frame(pack('n', $data), true, Frame::OP_CLOSE));
        }

        // Closing the connection can synchronously trigger the close event, so the connection must be marked as closing first
        $this->getAttributeStore()->set(AttributeKey::WEBSOCKET_CLOSING, true);

        $this->connection->close();
    }
}
