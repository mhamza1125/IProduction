<?php

namespace App\Modules\Payroll\Livewire\Advances;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\People\Models\Employee;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?SalaryAdvance $advance = null;

    public ?int $employee_id = null;

    public string $advance_date = '';

    public string $amount = '';

    public string $salary_month = '';

    public ?int $money_account_id = null;

    public string $notes = '';

    public function mount(?SalaryAdvance $advance = null): void
    {
        if ($advance?->exists) {
            abort_unless($advance->isDraft(), 403, 'Only drafts can be edited.');
            $this->advance = $advance;
            $this->fill($advance->only(['employee_id', 'money_account_id']));
            $this->amount = (string) $advance->amount;
            $this->notes = (string) $advance->notes;
            $this->advance_date = $advance->advance_date->format('Y-m-d');
            $this->salary_month = $advance->salary_month->format('Y-m');

            return;
        }
        $this->advance_date = now()->format('Y-m-d');
        $this->salary_month = now()->format('Y-m');
        $this->money_account_id = MoneyAccount::cashBook()->id;
    }

    public function save(LoansAndAdvances $service): void
    {
        $this->authorize($this->advance ? 'advances.edit' : 'advances.create');
        $data = $this->validate([
            'employee_id' => ['required', 'exists:employees,id'], 'advance_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'],
            'salary_month' => ['required', 'date_format:Y-m'], 'money_account_id' => ['required', 'exists:money_accounts,id'], 'notes' => ['nullable', 'string'],
        ]);
        $data['salary_month'] .= '-01';
        try {
            $advance = $service->saveAdvance($this->advance, $data);
        } catch (DocumentException $e) {
            $this->addError('amount', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$advance->number} saved.");
        $this->redirectRoute('payroll.advances.show', $advance, navigate: true);
    }

    public function render(): View
    {
        return view('payroll::livewire.advances.form', [
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'accounts' => MoneyAccount::query()->active()->where('currency_code', 'PKR')->pluck('name', 'id'),
        ])->title($this->advance ? 'Edit '.$this->advance->number : 'New salary advance');
    }
}
