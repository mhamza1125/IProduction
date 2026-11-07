<?php

namespace App\Modules\Finance\Livewire\Expenses;

use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\People\Models\Vendor;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Expense $expense = null;

    public string $expense_date = '';

    public ?int $expense_account_id = null;

    public string $payment = 'now';

    public ?int $money_account_id = null;

    public ?int $vendor_id = null;

    public string $amount = '';

    public string $reference = '';

    public string $notes = '';

    public function mount(?Expense $expense = null): void
    {
        if ($expense?->exists) {
            abort_unless($expense->isDraft(), 403, 'Only drafts can be edited.');
            $this->expense = $expense;
            $this->fill($expense->only(['expense_account_id', 'money_account_id', 'vendor_id']));
            $this->payment = $expense->money_account_id ? 'now' : 'payable';
            $this->amount = (string) $expense->amount;
            $this->reference = (string) $expense->reference;
            $this->notes = (string) $expense->notes;
            $this->expense_date = $expense->expense_date->format('Y-m-d');

            return;
        }
        $this->expense_date = now()->format('Y-m-d');
    }

    public function save(MoneyMovements $moves): void
    {
        $this->authorize($this->expense ? 'expenses.edit' : 'expenses.create');
        $this->validate([
            'expense_date' => ['required', 'date'], 'expense_account_id' => ['required', 'exists:gl_accounts,id'], 'payment' => ['required', 'in:now,payable'],
            'money_account_id' => [$this->payment === 'now' ? 'required' : 'nullable', 'exists:money_accounts,id'],
            'vendor_id' => [$this->payment === 'payable' ? 'required' : 'nullable', 'exists:vendors,id'],
            'amount' => ['required', 'numeric', 'gt:0'], 'reference' => ['nullable', 'string', 'max:100'],
        ]);
        try {
            $doc = $moves->saveExpense($this->expense, [
                'expense_date' => $this->expense_date, 'expense_account_id' => $this->expense_account_id, 'amount' => $this->amount,
                'money_account_id' => $this->payment === 'now' ? $this->money_account_id : null,
                'vendor_id' => $this->vendor_id, 'reference' => $this->reference, 'notes' => $this->notes,
            ]);
        } catch (DocumentException $e) {
            $this->addError('expense_account_id', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$doc->number} saved.");
        $this->redirectRoute('finance.expenses.show', $doc, navigate: true);
    }

    public function render(): View
    {
        return view('finance::livewire.expenses.form', [
            'heads' => GlAccount::query()->active()->whereIn('type', ['expense', 'cost'])->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => $a->code.' '.$a->name]),
            'accounts' => MoneyAccount::query()->active()->where('currency_code', 'PKR')->pluck('name', 'id'),
            'vendors' => Vendor::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label()]),
        ])->title($this->expense ? 'Edit '.$this->expense->number : 'New expense');
    }
}
