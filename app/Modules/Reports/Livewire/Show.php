<?php

namespace App\Modules\Reports\Livewire;

use App\Modules\Reports\Services\Reports;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportFormatter;
use App\Support\Exports\RowsExport;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Excel as ExcelWriter;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class Show extends Component
{
    use WithPagination;

    #[Locked]
    public string $key = '';

    /** @var array<string, string|null> */
    #[Url(as: 'f')]
    public array $filters = [];

    public function mount(string $key, Reports $reports): void
    {
        $report = $reports->find($key) ?? abort(404);
        abort_unless($reports->allowed($report, auth()->user()), 403);
        $this->key = $key;
        $this->filters = array_merge($report->defaults(), array_intersect_key($this->filters, $report->defaults()));
    }

    private function report(): Report
    {
        return app(Reports::class)->find($this->key) ?? abort(404);
    }

    public function updatedFilters(): void
    {
        $this->resetPage();
    }

    public function export(string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('reports.export');
        $report = $this->report();
        abort_unless(app(Reports::class)->allowed($report, auth()->user()), 403);
        $columns = $report->columns();
        $rows = $report->rows($this->filters)->map(fn ($r) => array_map(fn ($c) => ReportFormatter::plain($c, $r), $columns))->values()->all();
        $csv = $format === 'csv';

        return Excel::download(new RowsExport(array_map(fn ($c) => $c->label, $columns), $rows), str($report->title())->slug().'-'.now()->format('Y-m-d').($csv ? '.csv' : '.xlsx'),
            $csv ? ExcelWriter::CSV : ExcelWriter::XLSX);
    }

    public function render(): View
    {
        $report = $this->report();
        $rows = $report->rows($this->filters);
        $page = $this->getPage();
        $perPage = 50;

        return view('reports::livewire.show', [
            'report' => $report,
            'columns' => $report->columns(),
            'rows' => new LengthAwarePaginator($rows->forPage($page, $perPage)->values(), $rows->count(), $perPage, $page),
            'totals' => $report->totals($rows),
            'filterDefs' => $report->filters(),
        ])->title($report->title());
    }
}
