<?php

namespace App\Modules\People\Livewire\Employees;

use App\Modules\Core\Models\Lookup;
use App\Modules\People\Models\Employee;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Employees')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $department = '';

    protected function sortable(): array
    {
        return ['code', 'name', 'join_date', 'employee_type'];
    }

    /** @return Builder<Employee> */
    private function query(): Builder
    {
        return $this->applyDateRange($this->applySearch(Employee::query()->with(['department', 'designation']), ['code', 'name', 'father_name', 'cnic', 'phone'])
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->type !== '', fn ($q) => $q->where('employee_type', $this->type))
            ->when($this->department !== '', fn ($q) => $q->where('department_id', (int) $this->department)), 'join_date');
    }

    protected function exportPermission(): string
    {
        return 'employees.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        $columns = [
            'Code' => 'code', 'Name' => 'name', 'Father name' => 'father_name', 'CNIC' => 'cnic', 'Phone' => 'phone',
            'Department' => fn (Employee $e) => $e->department?->name,
            'Designation' => fn (Employee $e) => $e->designation?->name,
            'Type' => fn (Employee $e) => Employee::TYPES[$e->employee_type] ?? $e->employee_type,
            'Join date' => fn (Employee $e) => Format::date($e->join_date),
            'Exit date' => fn (Employee $e) => Format::date($e->exit_date),
            'Active' => fn (Employee $e) => $e->is_active ? 'Yes' : 'No',
        ];

        if (auth()->user()?->can('salary.view')) {
            $columns['Monthly salary'] = 'monthly_salary';
        }

        return $columns;
    }

    public function render(): View
    {
        return view('people::livewire.employees.index', [
            'employees' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
            'departments' => Lookup::options('department'),
            'types' => Employee::TYPES,
        ]);
    }
}
