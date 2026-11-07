<?php

namespace App\Modules\Reports\Definitions\Production;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class ProcessYield extends Report
{
    public function key(): string
    {
        return 'yield';
    }

    public function title(): string
    {
        return 'Material processing yield';
    }

    public function group(): string
    {
        return 'Production';
    }

    public function description(): string
    {
        return 'Expected and actual output, wastage % and cost per unit for posted material processing.';
    }

    public function permissions(): array
    {
        return ['material_processes.view'];
    }

    public function filters(): array
    {
        return [...$this->dateRange()];
    }

    public function columns(): array
    {
        return [
            new Column('process', 'Process', linkKey: 'url'), new Column('date', 'Date', 'date'), new Column('name', 'Name'), new Column('input', 'Input', 'qty'),
            new Column('expected', 'Expected', 'qty'), new Column('actual', 'Actual', 'qty'), new Column('yield', 'Yield %', 'number'), new Column('unit_cost', 'Cost per unit', 'money'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $lines = DB::table('material_process_lines')->groupBy('material_process_id')->select('material_process_id',
            DB::raw("SUM(CASE WHEN kind = 'input' THEN quantity ELSE 0 END) as input"), DB::raw("SUM(CASE WHEN kind = 'output' THEN expected_qty ELSE 0 END) as expected"),
            DB::raw("SUM(CASE WHEN kind = 'output' THEN quantity ELSE 0 END) as actual"));
        $canCost = auth()->user()?->can('cost.view');

        return DB::table('material_processes as m')->joinSub($lines, 'l', 'l.material_process_id', '=', 'm.id')->whereNotNull('m.posted_at')
            ->whereBetween('m.process_date', [$this->from($filters)?->toDateString(), $this->to($filters)?->toDateString()])->orderBy('m.process_date')
            ->get(['m.id', 'm.number', 'm.process_date', 'm.process_name', 'l.input', 'l.expected', 'l.actual', 'm.output_unit_cost'])
            ->map(fn ($r) => ['process' => $r->number, 'url' => route('production.processes.show', $r->id), 'date' => $r->process_date, 'name' => $r->process_name, 'input' => $r->input,
                'expected' => (float) $r->expected ? $r->expected : null, 'actual' => $r->actual,
                'yield' => (float) $r->expected ? Decimal::div(Decimal::mul((string) $r->actual, '100', 4), (string) $r->expected, 2) : null,
                'unit_cost' => $canCost ? $r->output_unit_cost : null]);
    }
}
