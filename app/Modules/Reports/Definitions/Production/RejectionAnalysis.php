<?php

namespace App\Modules\Reports\Definitions\Production;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Facades\DB;

class RejectionAnalysis extends Report
{
    public function key(): string
    {
        return 'rejections-by-stage';
    }

    public function title(): string
    {
        return 'Rejections by stage and reason';
    }

    public function group(): string
    {
        return 'Production';
    }

    public function description(): string
    {
        return 'Rejected pieces grouped by stage and reason, with who was responsible.';
    }

    public function permissions(): array
    {
        return ['rejections.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('stage', 'Stage'), new Column('reason', 'Reason'), new Column('count', 'Rejections', 'number'), new Column('quantity', 'Pieces', 'qty', total: true),
            new Column('decided', 'Decided', 'qty', total: true),
        ];
    }

    protected function build(array $filters): iterable
    {
        return DB::table('rejections as r')->leftJoin('stages as s', 's.id', '=', 'r.stage_id')
            ->whereBetween('r.rejection_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])
            ->groupBy('s.name', 'r.reason')->orderByDesc(DB::raw('SUM(r.quantity)'))
            ->get([DB::raw("COALESCE(s.name, 'Store') as stage"), 'r.reason', DB::raw('COUNT(*) as count'), DB::raw('SUM(r.quantity) as quantity'), DB::raw('SUM(r.decided_qty) as decided')])
            ->map(fn ($r) => (array) $r);
    }
}
