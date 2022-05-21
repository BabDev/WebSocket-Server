<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

use BabDev\WebSocket\Server\ConnectionInterface;

/**
 * The attribute store allows storing data associated with a {@see ConnectionInterface}.
 */
interface AttributeStoreInterface
{
    /**
     * @return array<string, mixed>
     */
    public function all(): array;

    public function get(string $name, mixed $default = null): mixed;

    public function has(string $name): bool;

    public function remove(string $name): void;

    /**
     * @param array<string, mixed> $attributes
     */
    public function replace(array $attributes): void;

    public function set(string $name, mixed $value): void;
}
