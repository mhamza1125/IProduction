<?php

namespace App\Support\Exports;

use Closure;
use Illuminate\Database\Eloquent\Builder;
use Maatwebsite\Excel\Concerns\FromQuery;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

/**
 * Exports a query to Excel or CSV, reading it in chunks so large lists do
 * not run out of memory.
 *
 *   new QueryExport($query, ['Code' => fn ($row) => $row->code, 'Name' => 'name']);
 */
class QueryExport implements FromQuery, WithHeadings, WithMapping
{
    /**
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     * @param  array<string, string|Closure(mixed): mixed>  $columns  heading => attribute name or closure
     */
    public function __construct(
        private readonly Builder $query,
        private readonly array $columns,
    ) {}

    /**
     * @return Builder<covariant \Illuminate\Database\Eloquent\Model>
     */
    public function query(): Builder
    {
        return $this->query;
    }

    /**
     * @return list<string>
     */
    public function headings(): array
    {
        return array_keys($this->columns);
    }

    /**
     * @return list<mixed>
     */
    public function map($row): array
    {
        $out = [];

        foreach ($this->columns as $column) {
            $out[] = $column instanceof Closure ? $column($row) : data_get($row, $column);
        }

        return $out;
    }
}
