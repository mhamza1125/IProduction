<?php

namespace App\Modules\Finance\Livewire\Expenses;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Services\MoneyMovements;
use App\Support\Documents\Document;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public Expense $expense;

    public function mount(Expense $expense): void
    {
        $this->expense = $expense->load(['expenseAccount', 'moneyAccount', 'vendor']);
    }

    protected function model(): Model&Document
    {
        return $this->expense;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(MoneyMovements::class)->postExpense($this->expense, $overrideReason);
    }

    protected function heading(): string
    {
        return 'Expense '.$this->expense->number;
    }

    protected function details(): array
    {
        $e = $this->expense;

        return [
            'Date' => Format::date($e->expense_date), 'Expense head' => $e->expenseAccount->name, 'Amount' => Format::money($e->amount, 'PKR'),
            'Paid from' => $e->moneyAccount?->name, 'Payable to' => $e->vendor?->name,
            'Paid so far' => $e->isPayable() && $e->isPosted() ? Format::money($e->paid_amount, 'PKR') : null,
            'Reference' => $e->reference, 'Notes' => $e->notes,
        ];
    }

    protected function editRoute(): string
    {
        return 'finance.expenses.edit';
    }

    protected function indexRoute(): string
    {
        return 'finance.expenses.index';
    }
}
