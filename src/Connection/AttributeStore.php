<?php declare(strict_types=1);

namespace BabDev\WebSocket\Server\Connection;

final class AttributeStore implements AttributeStoreInterface
{
    /**
     * @var array<string, mixed>
     */
    private array $attributes = [];

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->attributes;
    }

    public function get(string $name, mixed $default = null): mixed
    {
        return $this->attributes[$name] ?? $default;
    }

    public function has(string $name): bool
    {
        return \array_key_exists($name, $this->attributes);
    }

    public function remove(string $name): void
    {
        unset($this->attributes[$name]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function replace(array $attributes): void
    {
        $this->attributes = [];

        foreach ($attributes as $name => $value) {
            $this->set($name, $value);
        }
    }

    public function set(string $name, mixed $value): void
    {
        $this->attributes[$name] = $value;
    }
}
