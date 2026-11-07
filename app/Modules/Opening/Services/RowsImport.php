<?php

namespace App\Modules\Opening\Services;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/** Reads the first sheet of an upload with its heading row as keys (snake_case). */
class RowsImport implements ToCollection, WithHeadingRow
{
    /** @var Collection<int, Collection<string, mixed>> */
    public Collection $rows;

    public function __construct()
    {
        $this->rows = collect();
    }

    /** @param  Collection<int, Collection<string, mixed>>  $collection */
    public function collection(Collection $collection): void
    {
        $this->rows = $collection;
    }
}
