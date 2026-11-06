<?php

namespace App\Modules\Finance\Livewire\Accounts;

use App\Modules\Core\Models\Currency;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyAccounts;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Cash and bank accounts. The Cash Book is permanent; banks can be added
 * without limit, in PKR or a foreign currency.
 */
#[Title('Accounts')]
class Index extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $type = MoneyAccount::BANK;

    public string $bank_name = '';

    public string $branch = '';

    public string $account_title = '';

    public string $account_number = '';

    public string $iban = '';

    public string $currency_code = 'PKR';

    public bool $allow_overdraft = false;

    public bool $is_active = true;

    public string $notes = '';

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'type', 'bank_name', 'branch', 'account_title', 'account_number', 'iban', 'currency_code', 'allow_overdraft', 'is_active', 'notes');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $account = MoneyAccount::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $account->id;
        $this->fill(array_map(fn ($v) => $v ?? '', $account->only(['name', 'type', 'bank_name', 'branch', 'account_title', 'account_number', 'iban', 'currency_code', 'allow_overdraft', 'is_active', 'notes'])));
        $this->showForm = true;
    }

    public function save(MoneyAccounts $accounts): void
    {
        $this->authorize($this->editingId ? 'accounts.edit' : 'accounts.create');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100'],
            'type' => ['required', Rule::in(array_keys(MoneyAccount::TYPES))],
            'bank_name' => ['nullable', 'string', 'max:100'],
            'branch' => ['nullable', 'string', 'max:100'],
            'account_title' => ['nullable', 'string', 'max:150'],
            'account_number' => ['nullable', 'string', 'max:50'],
            'iban' => ['nullable', 'string', 'max:34'],
            'currency_code' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'allow_overdraft' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        if ($this->editingId) {
            $account = MoneyAccount::query()->findOrFail($this->editingId);

            if ($account->currency_code !== $data['currency_code'] && $account->isInUse()) {
                $this->addError('currency_code', 'This account already has transactions; its currency cannot change.');

                return;
            }
            if ($account->system_key === 'cash_book') {
                $data['type'] = MoneyAccount::CASH;
                $data['is_active'] = true; // the Cash Book is permanent
            }

            $account->update($data);
            $account->glAccount()->update(['name' => $data['name']]);
        } else {
            $accounts->create($data);
        }

        $this->showForm = false;
        $this->dispatch('toast', message: 'Account saved.');
    }

    public function render(Ledgers $ledgers): View
    {
        $canSeeBalance = auth()->user()?->can('bank_balance.view') ?? false;
        $list = MoneyAccount::query()->with('glAccount')->orderByDesc('system_key')->orderBy('currency_code')->orderBy('name')->get();

        return view('finance::livewire.accounts.index', [
            'accounts' => $list,
            'balances' => $canSeeBalance ? $list->mapWithKeys(fn ($a) => [$a->id => $ledgers->moneyBalance($a)]) : collect(),
            'canSeeBalance' => $canSeeBalance,
            'currencies' => Currency::query()->where('is_active', true)->orderByDesc('is_base')->orderBy('code')->pluck('code', 'code'),
            'types' => MoneyAccount::TYPES,
        ]);
    }
}
