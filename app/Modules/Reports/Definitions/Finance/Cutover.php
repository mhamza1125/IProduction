<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;

/** Imported opening totals against the old system's control totals. */
class Cutover extends Report
{
    public function key(): string
    {
        return 'cutover';
    }

    public function title(): string
    {
        return 'Cutover report';
    }

    public function group(): string
    {
        return 'Go-live';
    }

    public function description(): string
    {
        return 'Each posted opening document: imported totals per currency against the totals from the old system.';
    }

    public function permissions(): array
    {
        return ['opening.view'];
    }

    public function columns(): array
    {
        return [new Column('number', 'Document', linkKey: 'url'), new Column('type', 'Type'), new Column('date', 'Opening date', 'date'), new Column('rows', 'Rows', 'number'),
            new Column('currency', 'Currency'), new Column('imported', 'Imported', 'money'), new Column('control', 'Old system', 'money'), new Column('difference', 'Difference', 'money'), new Column('check', 'Check')];
    }

    protected function build(array $filters): iterable
    {
        $rows = [];
        foreach (OpeningBatch::query()->whereNotNull('posted_at')->orderBy('id')->get() as $b) {
            $currencies = array_unique([...array_keys($b->totals ?? []), ...array_keys($b->control_totals ?? [])]) ?: ['PKR'];
            foreach ($currencies as $cur) {
                $imported = $b->totals[$cur] ?? '0';
                $control = $b->control_totals[$cur] ?? null;
                $rows[] = ['number' => $b->number, 'url' => route('opening.show', $b), 'type' => $b->typeLabel(), 'date' => $b->batch_date, 'rows' => $b->row_count, 'currency' => $cur,
                    'imported' => $imported, 'control' => $control, 'difference' => $control === null ? null : Decimal::sub($imported, $control),
                    'check' => $control === null ? 'No control total' : (Decimal::compare($imported, $control) === 0 ? 'Matches' : 'DIFFERENT')];
            }
        }

        return $rows;
    }
}
