<?php

namespace App\Modules\People\Livewire\Employees;

use App\Modules\Core\Models\Lookup;
use App\Modules\People\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Employee $employee = null;

    public string $code = '';

    public string $name = '';

    public string $father_name = '';

    public string $cnic = '';

    public string $phone = '';

    public string $address = '';

    public ?int $department_id = null;

    public ?int $designation_id = null;

    public string $employee_type = Employee::TYPE_SALARY;

    public ?string $join_date = null;

    public ?string $exit_date = null;

    public string $monthly_salary = '';

    public ?int $payment_method_id = null;

    public string $bank_name = '';

    public string $bank_account_title = '';

    public string $bank_account_number = '';

    public string $iban = '';

    public bool $is_active = true;

    public function mount(?Employee $employee = null): void
    {
        if ($employee?->exists) {
            $this->employee = $employee;
            $this->fill(array_map(fn ($v) => $v ?? '', $employee->only([
                'code', 'name', 'father_name', 'cnic', 'phone', 'address', 'employee_type',
                'bank_name', 'bank_account_title', 'bank_account_number', 'iban', 'is_active',
            ])));
            $this->department_id = $employee->department_id;
            $this->designation_id = $employee->designation_id;
            $this->payment_method_id = $employee->payment_method_id;
            $this->join_date = $employee->join_date->format('Y-m-d');
            $this->exit_date = $employee->exit_date?->format('Y-m-d');

            // Salary is only loaded for users allowed to see it.
            $this->monthly_salary = auth()->user()?->can('salary.view') ? (string) $employee->monthly_salary : '';
        } else {
            $this->join_date = now()->format('Y-m-d');
        }
    }

    public function save(): void
    {
        $this->authorize($this->employee ? 'employees.edit' : 'employees.create');

        $canSeeSalary = auth()->user()?->can('salary.view') ?? false;

        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('employees', 'code')->ignore($this->employee?->id)],
            'name' => ['required', 'string', 'max:150'],
            'father_name' => ['nullable', 'string', 'max:150'],
            'cnic' => ['nullable', 'regex:/^\d{5}-?\d{7}-?\d$/', Rule::unique('employees', 'cnic')->ignore($this->employee?->id)],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'department_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'department')],
            'designation_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'designation')],
            'employee_type' => ['required', Rule::in(array_keys(Employee::TYPES))],
            'join_date' => ['required', 'date_format:Y-m-d'],
            'exit_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:join_date'],
            'monthly_salary' => $canSeeSalary
                ? [Rule::requiredIf($this->employee_type === Employee::TYPE_SALARY), 'nullable', 'numeric', 'min:0', 'decimal:0,2']
                : ['nullable'],
            'payment_method_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'payment_method')],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'bank_account_title' => ['nullable', 'string', 'max:150'],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:34'],
            'is_active' => ['boolean'],
        ], ['cnic.regex' => 'CNIC must be 13 digits, e.g. 35202-1234567-1.']);

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        // Users without salary.view never change the salary.
        if (! $canSeeSalary) {
            unset($data['monthly_salary']);
        } elseif ($data['employee_type'] === Employee::TYPE_WAGE && $data['monthly_salary'] === null) {
            $data['monthly_salary'] = null;
        }

        $employee = $this->employee ?? new Employee;
        $employee->fill($data)->save();

        session()->flash('toast', $this->employee ? 'Employee updated.' : "Employee {$employee->code} created.");
        $this->redirectRoute('people.employees.show', $employee, navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.employees.form', [
            'departments' => Lookup::options('department', $this->department_id),
            'designations' => Lookup::options('designation', $this->designation_id),
            'paymentMethods' => Lookup::options('payment_method', $this->payment_method_id),
            'types' => Employee::TYPES,
            'canSeeSalary' => auth()->user()?->can('salary.view') ?? false,
        ])->title($this->employee ? 'Edit employee' : 'New employee');
    }
}
