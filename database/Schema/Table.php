<?php

declare(strict_types=1);

namespace Database\Schema;

/**
 * Table definition shared by every driver.
 *
 * The definition is driver agnostic on purpose: the DdlBuilder translates it
 * into the concrete SQL of the target driver.
 */
final class Table
{
    private string $name;

    /**
     * @var array<string, Column>
     */
    private array $columns = [];

    /**
     * @var array<int, array{column: string, table: string, reference: string}>
     */
    private array $foreignKeys = [];

    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function id(string $name = 'id'): self
    {
        return $this->add(new Column($name, 'id'));
    }

    public function string(string $name, int $length = 255): self
    {
        return $this->add(new Column($name, 'string', ['length' => $length]));
    }

    public function integer(string $name): self
    {
        return $this->add(new Column($name, 'integer'));
    }

    public function foreignId(string $name): self
    {
        return $this->add(new Column($name, 'foreignId'));
    }

    public function timestamp(string $name): self
    {
        return $this->add(new Column($name, 'timestamp'));
    }

    /**
     * Marks the column added last as nullable.
     */
    public function nullable(): self
    {
        $this->lastColumn()?->setOption('nullable', true);

        return $this;
    }

    /**
     * Marks the column added last as unique.
     */
    public function unique(): self
    {
        $this->lastColumn()?->setOption('unique', true);

        return $this;
    }

    public function foreignKey(string $column, string $table, string $reference = 'id'): self
    {
        $this->foreignKeys[] = [
            'column' => $column,
            'table' => $table,
            'reference' => $reference,
        ];

        return $this;
    }

    public function timestamps(): self
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');

        return $this;
    }

    public function name(): string
    {
        return $this->name;
    }

    /**
     * @return array<string, Column>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return array<int, array{column: string, table: string, reference: string}>
     */
    public function foreignKeys(): array
    {
        return $this->foreignKeys;
    }

    private function add(Column $column): self
    {
        $this->columns[$column->name()] = $column;

        return $this;
    }

    private function lastColumn(): ?Column
    {
        if ($this->columns === []) {
            return null;
        }

        return $this->columns[array_key_last($this->columns)];
    }
}
