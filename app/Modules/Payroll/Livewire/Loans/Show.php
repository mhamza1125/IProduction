<?php

namespace App\Modules\Payroll\Livewire\Loans;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Payroll\Models\EmployeeLoan;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public EmployeeLoan $loan;

    public string $change_month = '';

    public string $change_amount = '';

    public string $change_reason = '';

    public function mount(EmployeeLoan $loan): void
    {
        $this->loan = $loan->load(['employee', 'moneyAccount']);
        $this->change_month = now()->format('Y-m');
    }

    protected function model(): Model&Document
    {
        return $this->loan;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(LoansAndAdvances::class)->postLoan($this->loan, $overrideReason);
    }

    public function changeInstallment(LoansAndAdvances $service): void
    {
        $this->authorize('loans.override');
        $this->validate(['change_month' => ['required', 'date_format:Y-m'], 'change_amount' => ['required', 'numeric', 'min:0'], 'change_reason' => ['required', 'string', 'max:500']]);
        try {
            $service->changeInstallment($this->loan, Carbon::createFromFormat('Y-m', $this->change_month)->startOfMonth(), (string) $this->change_amount, $this->change_reason);
        } catch (DocumentException $e) {
            $this->addError('change_amount', $e->getMessage());

            return;
        }
        $this->reset('change_amount', 'change_reason');
        $this->dispatch('toast', message: 'Installment changed for that month.');
    }

    protected function heading(): string
    {
        return 'Loan '.$this->loan->number;
    }

    protected function details(): array
    {
        $l = $this->loan;

        return ['Employee' => $l->employee->name, 'Date' => Format::date($l->loan_date), 'Amount' => Format::money($l->amount), 'Paid from' => $l->moneyAccount->name,
            'Installment' => Format::money($l->installment), 'Starts' => $l->start_month->format('M Y'),
            'Recovered' => Format::money($l->recovered_amount), 'Balance' => Format::money($l->balance()), 'Notes' => $l->notes];
    }

    protected function editRoute(): string
    {
        return 'payroll.loans.edit';
    }

    protected function indexRoute(): string
    {
        return 'payroll.loans.index';
    }

    protected function body(): ?string
    {
        return 'payroll::livewire.loans.body';
    }

    protected function bodyData(): array
    {
        return ['installments' => $this->loan->installments()->orderBy('month')->get(), 'changes' => $this->loan->changes()->orderBy('month')->get()];
    }
}
