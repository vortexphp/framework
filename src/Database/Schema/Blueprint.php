<?php

declare(strict_types=1);

namespace Vortex\Database\Schema;

final class Blueprint
{
    /** @var list<ColumnDefinition> */
    private array $columns = [];

    /** @var list<array{columns:list<string>, unique:bool, name:?string}> */
    private array $indexes = [];

    public function __construct(
        private readonly string $table,
        private readonly bool $creating,
    ) {
    }

    public function table(): string
    {
        return $this->table;
    }

    public function creating(): bool
    {
        return $this->creating;
    }

    public function id(string $name = 'id'): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'id');
        $column->primary = true;
        $column->autoIncrement = true;
        $column->unsigned = true;
        $this->columns[] = $column;

        return $column;
    }

    public function string(string $name, int $length = 255): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'string', $length);
        $this->columns[] = $column;

        return $column;
    }

    public function text(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'text');
        $this->columns[] = $column;

        return $column;
    }

    public function integer(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'integer');
        $this->columns[] = $column;

        return $column;
    }

    public function bigInteger(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'bigInteger');
        $this->columns[] = $column;

        return $column;
    }

    public function smallInteger(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'smallInteger');
        $this->columns[] = $column;

        return $column;
    }

    public function decimal(string $name, int $precision = 8, int $scale = 2): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'decimal');
        $column->precision = max(1, $precision);
        $column->scale = max(0, min($scale, $column->precision));
        $this->columns[] = $column;

        return $column;
    }

    public function floatType(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'float');
        $this->columns[] = $column;

        return $column;
    }

    public function date(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'date');
        $this->columns[] = $column;

        return $column;
    }

    public function dateTime(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'dateTime');
        $this->columns[] = $column;

        return $column;
    }

    public function json(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'json');
        $this->columns[] = $column;

        return $column;
    }

    public function char(string $name, int $length): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'char', max(1, $length));
        $this->columns[] = $column;

        return $column;
    }

    public function boolean(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'boolean');
        $this->columns[] = $column;

        return $column;
    }

    public function timestamp(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'timestamp');
        $this->columns[] = $column;

        return $column;
    }

    public function foreignId(string $name): ColumnDefinition
    {
        $column = new ColumnDefinition($name, 'foreignId');
        $column->unsigned = true;
        $this->columns[] = $column;

        return $column;
    }

    public function timestamps(): void
    {
        $this->timestamp('created_at');
        $this->timestamp('updated_at');
    }

    /**
     * @param string|list<string> $columns
     */
    public function index(string|array $columns, ?string $name = null): void
    {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $this->indexes[] = ['columns' => $cols, 'unique' => false, 'name' => $name];
    }

    /**
     * @param string|list<string> $columns
     */
    public function unique(string|array $columns, ?string $name = null): void
    {
        $cols = is_array($columns) ? array_values($columns) : [$columns];
        $this->indexes[] = ['columns' => $cols, 'unique' => true, 'name' => $name];
    }

    /**
     * @return list<ColumnDefinition>
     */
    public function columns(): array
    {
        return $this->columns;
    }

    /**
     * @return list<array{columns:list<string>, unique:bool, name:?string}>
     */
    public function indexes(): array
    {
        $indexes = $this->indexes;
        foreach ($this->columns as $column) {
            if ($column->unique) {
                $indexes[] = [
                    'columns' => [$column->name],
                    'unique' => true,
                    'name' => $column->uniqueName,
                ];
            }
            if ($column->index) {
                $indexes[] = [
                    'columns' => [$column->name],
                    'unique' => false,
                    'name' => $column->indexName,
                ];
            }
        }

        return $indexes;
    }
}
