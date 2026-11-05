<?php

namespace App\Modules\People\Livewire\Employees;

use App\Modules\People\Models\Employee;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Employee $employee;

    public function mount(Employee $employee): void
    {
        $this->employee = $employee;
    }

    public function toggleActive(): void
    {
        $this->authorize('employees.edit');
        $this->employee->update(['is_active' => ! $this->employee->is_active]);
    }

    public function delete(): void
    {
        $this->authorize('employees.delete');

        try {
            $this->employee->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        session()->flash('toast', 'Employee deleted.');
        $this->redirectRoute('people.employees.index', navigate: true);
    }

    public function render(): View
    {
        $this->employee->loadMissing(['department', 'designation', 'paymentMethod']);

        return view('people::livewire.employees.show')->title($this->employee->name);
    }
}
