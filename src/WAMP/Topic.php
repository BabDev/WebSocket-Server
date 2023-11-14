<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\WAMP;

use BabDev\WebSocket\Server\Connection;
use BabDev\WebSocket\Server\Exception\UnsupportedConnection;

/**
 * A topic/channel containing the connections that have subscribed to it.
 *
 * @implements \IteratorAggregate<array-key, WAMPConnection>
 */
final readonly class Topic implements \IteratorAggregate, \Countable, \Stringable
{
    /**
     * @var \SplObjectStorage<WAMPConnection, null>
     */
    private \SplObjectStorage $subscribers;

    public function __construct(public string $id)
    {
        $this->subscribers = new \SplObjectStorage();
    }

    public function __toString(): string
    {
        return $this->id;
    }

    /**
     * @throws UnsupportedConnection if the connection is not a {@see WAMPConnection} instance
     */
    public function add(Connection $connection): void
    {
        if (!$connection instanceof WAMPConnection) {
            throw new UnsupportedConnection(sprintf('Connections registered in "%s" must be an instance of "%s", "%s" given.', self::class, WAMPConnection::class, get_debug_type($connection)));
        }

        $this->subscribers->attach($connection);
    }

    public function has(Connection $connection): bool
    {
        if (!$connection instanceof WAMPConnection) {
            return false;
        }

        return $this->subscribers->contains($connection);
    }

    public function remove(Connection $connection): void
    {
        if (!$connection instanceof WAMPConnection) {
            return;
        }

        if ($this->subscribers->contains($connection)) {
            $this->subscribers->detach($connection);
        }
    }

    /**
     * Send a message to all connections subscribed to this topic.
     *
     * @param mixed        $msg      Data to send with the broadcast, must be a JSON serializable value
     * @param list<string> $exclude  A list of session IDs the message should be excluded from
     * @param list<string> $eligible A list of session IDs the message should be sent to
     */
    public function broadcast(mixed $msg, array $exclude = [], array $eligible = []): void
    {
        // If we have no session IDs to filter, let's skip a few unnecessary calls
        if ([] === $exclude && [] === $eligible) {
            /** @var WAMPConnection $subscriber */
            foreach ($this->subscribers as $subscriber) {
                $subscriber->event($this->id, $msg);
            }

            return;
        }

        $useExclude = [] !== $exclude;
        $useEligible = [] !== $eligible;

        /** @var WAMPConnection $subscriber */
        foreach ($this->subscribers as $subscriber) {
            $sessionId = $subscriber->getAttributeStore()->get('wamp.session_id');

            if ($useExclude && \in_array($sessionId, $exclude, true)) {
                continue;
            }

            if ($useEligible && !\in_array($sessionId, $eligible, true)) {
                continue;
            }

            $subscriber->event($this->id, $msg);
        }
    }

    /**
     * @return \Traversable<array-key, WAMPConnection>
     */
    public function getIterator(): \Traversable
    {
        return $this->subscribers;
    }

    public function count(): int
    {
        return $this->subscribers->count();
    }
}
