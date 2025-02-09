<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

readonly class DataExplorerTableData
{
    /**
     * @param  string[]  $columns
     * @param  array<int, string[]>  $rows
     */
    public function __construct(
        public array $columns,
        public array $rows,
        public ?string $name = null,
        public ?int $total = null,
        public ?int $page = null,
        public ?int $last_page = null,
        public ?string $order_by = null,
        public ?string $error = null,
    ) {}

    public static function valueToString(mixed $value): string
    {
        return match (true) {
            is_bool($value) => $value ? 'Yes' : 'No',
            is_null($value) => '<NULL>',
            default => (string) $value,
        };
    }

    public static function structureFromTable(string $name, ?string $connection = null): self
    {
        $tableColumns = DB::connection($connection)
            ->getSchemaBuilder()
            ->getColumns($name);

        $columns = [];
        $rows = [];

        foreach ($tableColumns as $column) {
            $columns = array_map(
                fn (int|string $value): string => (string) $value,
                array_keys($column)
            );

            $rows[] = array_map(
                fn (mixed $value): string => self::valueToString($value),
                array_values($column)
            );
        }

        return new self(
            $columns,
            $rows,
        );
    }

    public static function fromQuery(string $query, ?string $connection = null, ?int $per_page = null, ?int $page = null): self
    {
        $columns = [];
        $rows = [];
        $error = null;

        try {
            $tableRows = DB::connection($connection)
                ->select($query);

            foreach ($tableRows as $row) {
                $values = get_object_vars($row);

                $columns = array_keys($values);
                $rows[] = array_values($values);
            }
        } catch (Throwable $throwable) {
            $error = $throwable->getMessage();
        }

        return new self(
            $columns,
            $rows,
            'SQL',
            error: $error
        );
    }

    public static function fromTable(string $name, ?string $connection = null, ?int $per_page = null, ?int $page = null, ?string $order_by = null): self
    {
        $tableRows = DB::connection($connection)
            ->table($name)
            ->when($order_by !== null, fn (Builder $query) => str_starts_with((string) $order_by, '-')
                 ? $query->orderByDesc(substr((string) $order_by, 1))
                 : $query->orderBy($order_by)
            )
            ->paginate(
                perPage: $per_page ?? 15,
                page: $page ?? 1
            );

        $columns = [];
        $rows = [];

        foreach ($tableRows->items() as $row) {
            $values = get_object_vars($row);

            $columns = array_keys($values);
            $rows[] = array_values($values);
        }

        if ($columns === []) {
            $columns = DB::connection($connection)
                ->getSchemaBuilder()
                ->getColumnListing($name);
        }

        return new self(
            $columns,
            $rows,
            $name,
            $tableRows->total(),
            $tableRows->currentPage(),
            $tableRows->lastPage(),
            $order_by
        );
    }

    public static function overview(?string $connection = null): self
    {
        $tables = DB::connection($connection)
            ->getSchemaBuilder()->getTables();

        $columns = [];
        $rows = [];

        foreach ($tables as $table) {
            $columns = array_map(
                fn (int|string $value): string => (string) $value,
                array_keys($table)
            );

            $rows[] = array_map(
                fn (mixed $value): string => self::valueToString($value),
                array_values($table)
            );
        }

        return new self(
            $columns,
            $rows,
        );
    }
}
