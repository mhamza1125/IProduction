<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Core\Models\Lookup;
use App\Modules\Payroll\Models\AttendanceSummary;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Services\Attendance;
use App\Modules\People\Models\Employee;
use App\Support\Documents\DocumentException;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * One row per employee for fast monthly entry.
 */
#[Title('Attendance')]
class AttendanceGrid extends Component
{
    #[Url]
    public string $month = '';

    #[Url(except: '')]
    public string $department = '';

    /** @var array<int, array<string, string|null>> employee id => fields */
    public array $rows = [];

    public function mount(): void
    {
        $this->month = $this->month ?: now()->format('Y-m');
        $this->load();
    }

    public function updatedMonth(): void
    {
        $this->load();
    }

    public function updatedDepartment(): void
    {
        $this->load();
    }

    private function monthDate(): Carbon
    {
        return Carbon::createFromFormat('Y-m', $this->month)->startOfMonth();
    }

    private function load(): void
    {
        $saved = AttendanceSummary::query()->whereDate('month', $this->monthDate())->get()->keyBy('employee_id');
        $this->rows = [];
        foreach ($this->employees() as $e) {
            $a = $saved->get($e->id);
            $this->rows[$e->id] = array_merge(array_fill_keys([...AttendanceSummary::FIELDS, 'notes'], ''), $a ? array_map(fn ($v) => $v === null ? '' : (string) $v, $a->only([...AttendanceSummary::FIELDS, 'notes'])) : []);
        }
    }

    /** @return Collection<int, Employee> */
    private function employees()
    {
        return Employee::query()->where('is_active', true)->when($this->department !== '', fn ($q) => $q->where('department_id', $this->department))->orderBy('name')->get();
    }

    public function save(Attendance $attendance): void
    {
        $this->authorize('attendance.edit');
        $this->validate(['month' => ['required', 'date_format:Y-m'], 'rows.*.*' => ['nullable']]);
        try {
            $count = $attendance->saveMonth($this->monthDate(), $this->rows);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->dispatch('toast', message: "Saved {$count} employees.");
    }

    public function render(): View
    {
        return view('payroll::livewire.attendance', [
            'employees' => $this->employees(),
            'locked' => PayrollLine::query()->whereDate('month', $this->monthDate())->whereNotNull('posted_key')->pluck('employee_id')->flip(),
            'departments' => Lookup::query()->where('type', 'department')->orderBy('name')->pluck('name', 'id'),
            'fields' => ['working_days' => 'Working days', 'days_off' => 'Days off', 'paid_leaves' => 'Paid leave', 'unpaid_leaves' => 'Unpaid leave', 'late_hours' => 'Late hours', 'overtime_hours' => 'Overtime hours'],
        ]);
    }
}
