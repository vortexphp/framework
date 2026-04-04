<?php

declare(strict_types=1);

namespace Vortex\Database\Schema;

final class ColumnDefinition
{
    public bool $nullable = false;
    public mixed $default = null;
    public bool $hasDefault = false;
    public bool $primary = false;
    public bool $autoIncrement = false;
    public bool $unsigned = false;
    public bool $unique = false;
    public bool $index = false;
    public ?string $uniqueName = null;
    public ?string $indexName = null;
    public ?string $referencesTable = null;
    public ?string $referencesColumn = null;
    public ?string $onDelete = null;
    public ?string $onUpdate = null;

    /** @internal Used by {@see Blueprint::decimal()}. */
    public ?int $precision = null;

    /** @internal Used by {@see Blueprint::decimal()}. */
    public ?int $scale = null;

    public function __construct(
        public readonly string $name,
        public readonly string $type,
        public readonly ?int $length = null,
    ) {
    }

    public function nullable(bool $value = true): self
    {
        $this->nullable = $value;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;
        $this->hasDefault = true;

        return $this;
    }

    public function unique(?string $name = null): self
    {
        $this->unique = true;
        $this->uniqueName = $name;

        return $this;
    }

    public function index(?string $name = null): self
    {
        $this->index = true;
        $this->indexName = $name;

        return $this;
    }

    public function primary(): self
    {
        $this->primary = true;

        return $this;
    }

    public function constrained(string $table, string $column = 'id'): self
    {
        $this->referencesTable = $table;
        $this->referencesColumn = $column;

        return $this;
    }

    public function references(string $column): self
    {
        $this->referencesColumn = $column;

        return $this;
    }

    public function on(string $table): self
    {
        $this->referencesTable = $table;

        return $this;
    }

    public function cascadeOnDelete(): self
    {
        $this->onDelete = 'CASCADE';

        return $this;
    }

    public function restrictOnDelete(): self
    {
        $this->onDelete = 'RESTRICT';

        return $this;
    }

    public function nullOnDelete(): self
    {
        $this->onDelete = 'SET NULL';

        return $this;
    }

    public function noActionOnDelete(): self
    {
        $this->onDelete = 'NO ACTION';

        return $this;
    }

    public function cascadeOnUpdate(): self
    {
        $this->onUpdate = 'CASCADE';

        return $this;
    }

    public function restrictOnUpdate(): self
    {
        $this->onUpdate = 'RESTRICT';

        return $this;
    }

    public function nullOnUpdate(): self
    {
        $this->onUpdate = 'SET NULL';

        return $this;
    }

    public function noActionOnUpdate(): self
    {
        $this->onUpdate = 'NO ACTION';

        return $this;
    }

    public function unsigned(): self
    {
        $this->unsigned = true;

        return $this;
    }
}
