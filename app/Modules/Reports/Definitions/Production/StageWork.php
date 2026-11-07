<?php

namespace App\Modules\Reports\Definitions\Production;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class StageWork extends Report
{
    public function key(): string
    {
        return 'stage-work';
    }

    public function title(): string
    {
        return 'Stage-wise work';
    }

    public function group(): string
    {
        return 'Production';
    }

    public function description(): string
    {
        return 'Pieces issued, received and rejected at each stage.';
    }

    public function permissions(): array
    {
        return ['ptcs.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('stage', 'Stage'), new Column('issued', 'Issued', 'qty', total: true), new Column('received', 'Received good', 'qty', total: true),
            new Column('rejected', 'Rejected', 'qty', total: true), new Column('rate', 'Rejection %', 'number'),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('ptc_transactions as t')->join('ptc_stages as ps', 'ps.id', '=', 't.ptc_stage_id')->join('stages as s', 's.id', '=', 'ps.stage_id')
            ->whereBetween('t.transaction_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('s.name', 's.sort_order')->orderBy('s.sort_order')
            ->get(['s.name as stage', DB::raw("SUM(CASE WHEN t.type = 'stage_issue' THEN t.quantity ELSE 0 END) as issued"),
                DB::raw("SUM(CASE WHEN t.type = 'stage_receive' THEN t.quantity ELSE 0 END) as received"), DB::raw("SUM(CASE WHEN t.type = 'reject' THEN t.quantity ELSE 0 END) as rejected")])
            ->map(function ($r) {
                $done = Decimal::add((string) $r->received, (string) $r->rejected, 4);

                return (array) $r + ['rate' => Decimal::compare($done, '0') > 0 ? Decimal::div(Decimal::mul((string) $r->rejected, '100', 4), $done, 2) : null];
            });
    }
}
