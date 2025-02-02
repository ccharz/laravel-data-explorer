<?php

declare(strict_types=1);

namespace Ccharz\DataExplorer;

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
            $columns = array_keys($column);
            $rows[] = array_map(
                fn (mixed $value) => self::valueToString($value),
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

        try {
            $tableRows = DB::connection($connection)
                ->select($query);

            foreach ($tableRows as $row) {
                $values = get_object_vars($row);

                $columns = array_keys($values);
                $rows[] = array_values($values);
            }
        } catch (Throwable) {

        }

        return new self(
            $columns,
            $rows,

        );
    }

    public static function fromTable(string $name, ?string $connection = null, ?int $per_page = null, ?int $page = null): self
    {
        $tableRows = DB::connection($connection)
            ->table($name)
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
            $tableRows->lastPage()
        );
    }

    public static function overview(?string $connection = null): self
    {
        $tables = DB::connection($connection)
            ->getSchemaBuilder()->getTables();

        $columns = [];
        $rows = [];

        foreach ($tables as $table) {
            $columns = array_keys($table);
            $rows[] = array_map(
                fn (mixed $value) => self::valueToString($value),
                array_values($table)
            );
        }

        return new self(
            $columns,
            $rows,
        );
    }
}
