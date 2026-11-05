<?php

namespace App\Support\Livewire;

use App\Support\Exports\QueryExport;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Excel/CSV export of the current (filtered) list. Pair it with WithDataTable
 * and show the button with <x-table.toolbar :export="auth()->user()->can('x.export')">.
 */
trait WithExport
{
    /** Permission required to export, e.g. "customers.export". */
    abstract protected function exportPermission(): string;

    /**
     * @return Builder<covariant Model>
     */
    abstract protected function exportQuery(): Builder;

    /**
     * Heading => attribute name or closure receiving the row.
     *
     * @return array<string, string|\Closure(mixed): mixed>
     */
    abstract protected function exportColumns(): array;

    public function export(string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize($this->exportPermission());

        $csv = $format === 'csv';
        $name = str(class_basename(static::class) === 'Index' ? class_basename(str_replace('\\Index', '', static::class)) : class_basename(static::class))
            ->slug().'-'.now()->format('Y-m-d').($csv ? '.csv' : '.xlsx');

        return Excel::download(
            new QueryExport($this->exportQuery(), $this->exportColumns()),
            $name,
            $csv ? ExcelWriter::CSV : ExcelWriter::XLSX,
        );
    }
}
