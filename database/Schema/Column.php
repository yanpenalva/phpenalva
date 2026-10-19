<?php

declare(strict_types=1);

namespace Database\Schema;

/**
 * Column definition used to build driver specific DDL.
 */
final class Column
{
    private string $name;

    private string $type;

    /**
     * @var array<string, mixed>
     */
    private array $options;

    /**
     * @param array<string, mixed> $options
     */
    public function __construct(string $name, string $type, array $options = [])
    {
        $this->name = $name;
        $this->type = $type;
        $this->options = $options;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function type(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function options(): array
    {
        return $this->options;
    }

    /**
     * @return mixed
     */
    public function option(string $key, $default = null)
    {
        return $this->options[$key] ?? $default;
    }

    /**
     * @param mixed $value
     */
    public function setOption(string $key, $value): void
    {
        $this->options[$key] = $value;
    }

    public function isNullable(): bool
    {
        return (bool) $this->option('nullable', false);
    }

    public function isUnique(): bool
    {
        return (bool) $this->option('unique', false);
    }

    public function length(): int
    {
        return (int) $this->option('length', 255);
    }
}
