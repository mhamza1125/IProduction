<?php

namespace App\Modules\Opening\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Modules\Opening\Models\OpeningBatch;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;

/**
 * Opening balances and go-live (specification, Module 13).
 *  - each type is imported from an Excel/CSV file into a dated batch;
 *  - every row is validated; the preview shows errors; only an error-free batch posts;
 *  - the same file cannot be imported twice for a type (unless the earlier batch was cancelled);
 *  - opening entries post against Opening Balance, by currency;
 *  - after go-live is locked, no more opening batches can be created or posted.
 */
class Openings
{
    public function __construct(
        private readonly OpeningTypes $types,
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly Settings $settings,
    ) {}

    public function locked(): bool
    {
        return $this->settings->get('opening.locked_at') !== null;
    }

    public function import(string $type, string $path, string $fileName, string $date, ?string $notes = null): OpeningBatch
    {
        if ($this->locked()) {
            throw new DocumentException('Go-live is locked. Fix balances with normal adjustments, not opening documents.');
        }
        $handler = $this->types->get($type);
        $hash = hash_file('sha256', $path) ?: '';
        $duplicate = OpeningBatch::query()->where('type', $type)->where('file_hash', $hash)->where('status', '!=', 'cancelled')->first();
        if ($duplicate) {
            throw new DocumentException("This file was already imported as {$duplicate->number}.");
        }

        $import = new RowsImport;
        Excel::import($import, $path);
        $rows = $import->rows->map(fn ($r) => collect($r)->map(fn ($v) => $v === null ? null : trim((string) $v))->all())
            ->filter(fn ($r) => array_filter($r, fn ($v) => $v !== null && $v !== '') !== [])->values();
        if ($rows->isEmpty()) {
            throw new DocumentException('The file has no rows. Use the template and keep the heading row.');
        }
        $missing = array_diff(array_keys($handler->columns()), array_keys($rows->first()));
        if ($missing !== []) {
            throw new DocumentException('Missing columns: '.implode(', ', $missing).'. Download the template for this type.');
        }

        return DB::transaction(function () use ($type, $handler, $rows, $hash, $fileName, $date, $notes) {
            $batch = OpeningBatch::query()->create([
                'number' => $this->numbers->next('opening_batch', Carbon::parse($date)), 'type' => $type, 'batch_date' => $date,
                'file_name' => $fileName, 'file_hash' => $hash, 'notes' => $notes, 'created_by' => auth()->id(),
            ]);
            $context = new OpeningContext;
            $errors = 0;
            foreach ($rows as $i => $row) {
                $row = array_intersect_key($row, $handler->columns()) + array_fill_keys(array_keys($handler->columns()), null);
                $rowErrors = $handler->validate($row, $context);
                $errors += $rowErrors === [] ? 0 : 1;
                $batch->rows()->create(['row_number' => $i + 2, 'data' => $row, 'errors' => $rowErrors ?: null]);
            }
            $batch->forceFill(['row_count' => $rows->count(), 'error_count' => $errors])->save();

            return $batch->refresh();
        });
    }

    /**
     * Re-checks every row against the current data (e.g. after adding a missing
     * customer) and returns the number of rows with errors.
     */
    public function revalidate(OpeningBatch $batch): int
    {
        if (! $batch->isDraft()) {
            throw new DocumentException("{$batch->number} is {$batch->status}.");
        }
        $handler = $this->types->get($batch->type);
        $context = new OpeningContext;
        $errors = 0;
        foreach ($batch->rows()->get() as $row) {
            $rowErrors = $handler->validate($row->data, $context);
            $errors += $rowErrors === [] ? 0 : 1;
            $row->update(['errors' => $rowErrors ?: null]);
        }
        $batch->forceFill(['error_count' => $errors])->save();

        return $errors;
    }

    /** @param  array<string, string|null>  $controls  currency => old-system total */
    public function setControlTotals(OpeningBatch $batch, array $controls): void
    {
        $clean = [];
        foreach ($controls as $currency => $amount) {
            if ($amount !== null && $amount !== '') {
                if (! is_numeric($amount)) {
                    throw new DocumentException('Control totals must be numbers.');
                }
                $clean[strtoupper((string) $currency)] = (string) $amount;
            }
        }
        if ($batch->isPosted() && $this->locked()) {
            throw new DocumentException('Go-live is locked.');
        }
        $batch->forceFill(['control_totals' => $clean ?: null])->save();
    }

    public function post(OpeningBatch $batch, ?string $overrideReason = null): OpeningBatch
    {
        if ($this->locked()) {
            throw new DocumentException('Go-live is locked. Fix balances with normal adjustments, not opening documents.');
        }

        return $this->workflow->post($batch, function (OpeningBatch $b) {
            $handler = $this->types->get($b->type);
            $context = new OpeningContext;
            $rows = $b->rows()->get();
            foreach ($rows as $row) {
                if ($handler->validate($row->data, $context) !== []) {
                    throw new DocumentException("Row {$row->row_number} has errors. Fix the file or the data, re-check, then post.");
                }
            }
            $totals = $handler->post($b, $rows);
            $b->forceFill(['totals' => $totals])->save();
        }, $overrideReason);
    }

    /** Go-live: no more opening documents; the period up to the opening date is locked. */
    public function lockGoLive(string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        if ($this->locked()) {
            throw new DocumentException('Go-live is already locked.');
        }
        if (OpeningBatch::query()->whereIn('status', ['draft', 'approved'])->exists()) {
            throw new DocumentException('Post or cancel the open opening batches first.');
        }
        $date = OpeningBatch::query()->whereNotNull('posted_at')->max('batch_date');
        $current = $this->settings->get('period_lock.date');
        $values = ['opening.locked_at' => now()->toDateTimeString(), 'opening.locked_reason' => $reason];
        if ($date && (! $current || $date > $current)) {
            $values['period_lock.date'] = Carbon::parse($date)->toDateString();
            $values['period_lock.reason'] = 'Go-live: opening balances';
        }
        $this->settings->setMany($values);
        activity('settings')->event('go_live_locked')->withProperties(['reason' => $reason])->log('Go-live locked');
    }
}
