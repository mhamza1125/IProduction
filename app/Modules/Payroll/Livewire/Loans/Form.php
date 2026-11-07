<?php

namespace App\Modules\Payroll\Livewire\Loans;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\People\Models\Employee;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?EmployeeLoan $loan = null;

    public ?int $employee_id = null;

    public string $loan_date = '';

    public string $amount = '';

    public ?int $money_account_id = null;

    public string $installment = '';

    public string $start_month = '';

    public string $notes = '';

    public function mount(?EmployeeLoan $loan = null): void
    {
        if ($loan?->exists) {
            abort_unless($loan->isDraft(), 403, 'Only drafts can be edited.');
            $this->loan = $loan;
            $this->fill($loan->only(['employee_id', 'money_account_id']));
            $this->amount = (string) $loan->amount;
            $this->installment = (string) $loan->installment;
            $this->notes = (string) $loan->notes;
            $this->loan_date = $loan->loan_date->format('Y-m-d');
            $this->start_month = $loan->start_month->format('Y-m');

            return;
        }
        $this->loan_date = now()->format('Y-m-d');
        $this->start_month = now()->addMonth()->format('Y-m');
        $this->money_account_id = MoneyAccount::cashBook()->id;
    }

    public function save(LoansAndAdvances $service): void
    {
        $this->authorize($this->loan ? 'loans.edit' : 'loans.create');
        $data = $this->validate([
            'employee_id' => ['required', 'exists:employees,id'], 'loan_date' => ['required', 'date'], 'amount' => ['required', 'numeric', 'gt:0'],
            'money_account_id' => ['required', 'exists:money_accounts,id'], 'installment' => ['required', 'numeric', 'gt:0'], 'start_month' => ['required', 'date_format:Y-m'],
            'notes' => ['nullable', 'string'],
        ]);
        $data['start_month'] .= '-01';
        try {
            $loan = $service->saveLoan($this->loan, $data);
        } catch (DocumentException $e) {
            $this->addError('installment', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$loan->number} saved.");
        $this->redirectRoute('payroll.loans.show', $loan, navigate: true);
    }

    public function render(): View
    {
        return view('payroll::livewire.loans.form', [
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'accounts' => MoneyAccount::query()->active()->where('currency_code', 'PKR')->pluck('name', 'id'),
        ])->title($this->loan ? 'Edit '.$this->loan->number : 'New employee loan');
    }
}
