<?php

namespace App\Modules\Payroll\Livewire\Runs;

use App\Modules\Core\Models\Lookup;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Employee;
use App\Support\Format;
use App\Support\Livewire\DocumentList;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    public string $newMonth = '';

    public string $newDepartment = '';

    public string $newType = '';

    public function mount(): void
    {
        $this->newMonth = now()->format('Y-m');
    }

    public function createRun(Payrolls $payrolls): void
    {
        $this->authorize('payroll.create');
        $this->validate(['newMonth' => ['required', 'date_format:Y-m'], 'newDepartment' => ['nullable', 'exists:lookups,id'], 'newType' => ['nullable', 'in:salary,wage']]);
        $run = $payrolls->create(Carbon::createFromFormat('Y-m', $this->newMonth)->startOfMonth(), $this->newDepartment !== '' ? (int) $this->newDepartment : null, $this->newType ?: null);
        session()->flash('toast', "{$run->number} created with {$run->lines()->count()} employees.");
        $this->redirectRoute('payroll.runs.show', $run, navigate: true);
    }

    protected function baseQuery(): Builder
    {
        return PayrollRun::query()->with('department')->withCount('lines');
    }

    protected function searchPlaceholder(): string
    {
        return 'Search number…';
    }

    protected function dateColumn(): string
    {
        return 'month';
    }

    protected function permission(): string
    {
        return 'payroll';
    }

    protected function title(): string
    {
        return 'Payroll';
    }

    protected function description(): string
    {
        return 'Monthly payroll runs. Choose the month (and optionally a department or employee type) to create one.';
    }

    protected function showRoute(): string
    {
        return 'payroll.runs.show';
    }

    protected function createRoute(): ?string
    {
        return null;
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'partly_paid', 'paid', 'cancelled'];
    }

    /** @return array<string, callable(PayrollRun): string> */
    protected function columns(): array
    {
        $cols = [
            'Month' => fn (PayrollRun $r) => $r->monthLabel(),
            'For' => fn (PayrollRun $r) => trim(($r->department->name ?? 'All departments').' · '.($r->employee_type ? Employee::TYPES[$r->employee_type] : 'all types')),
            'Employees' => fn (PayrollRun $r) => (string) $r->getAttribute('lines_count'),
        ];
        if (auth()->user()?->can('salary.view')) {
            $cols['Net payable'] = fn (PayrollRun $r) => Format::money($r->total_net);
            $cols['Paid'] = fn (PayrollRun $r) => Format::money($r->total_paid);
        }

        return $cols;
    }

    protected function rightAligned(): array
    {
        return ['Employees', 'Net payable', 'Paid'];
    }

    public function render(): View
    {
        return parent::render()->with([
            'createForm' => 'payroll::livewire.runs.create',
            'departments' => Lookup::query()->where('type', 'department')->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
