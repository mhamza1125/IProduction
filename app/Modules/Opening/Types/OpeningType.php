<?php

namespace App\Modules\Opening\Types;

use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Models\OpeningBatchRow;
use App\Modules\Opening\Services\OpeningContext;
use Illuminate\Support\Collection;

/**
 * One kind of opening document (specification, Module 13). It declares its
 * template columns, validates each row and posts the batch.
 */
interface OpeningType
{
    public function key(): string;

    public function label(): string;

    /** @return array<string, string> column (snake_case heading) => what goes in it */
    public function columns(): array;

    /** @return array<string, string> one example row for the template */
    public function example(): array;

    /**
     * Errors for one row (empty when valid). $context holds what earlier rows of the
     * same file used, to catch duplicates inside the file.
     *
     * @param  array<string, string|null>  $row
     * @return list<string>
     */
    public function validate(array $row, OpeningContext $context): array;

    /**
     * Posts every row inside the batch's posting transaction; returns the
     * imported totals by currency (for the cutover report).
     *
     * @param  Collection<int, OpeningBatchRow>  $rows
     * @return array<string, string>
     */
    public function post(OpeningBatch $batch, Collection $rows): array;
}
