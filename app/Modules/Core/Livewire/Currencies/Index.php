<?php

namespace App\Modules\Core\Livewire\Currencies;

use App\Modules\Core\Models\Currency;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Currencies')]
class Index extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $symbol = '';

    public int $decimal_places = 2;

    public function create(): void
    {
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $currency = Currency::query()->findOrFail($id);

        $this->resetValidation();
        $this->editingId = $currency->id;
        $this->code = $currency->code;
        $this->name = $currency->name;
        $this->symbol = (string) $currency->symbol;
        $this->decimal_places = $currency->decimal_places;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'currencies.edit' : 'currencies.create');

        $this->code = strtoupper(trim($this->code));

        $data = $this->validate([
            'code' => ['required', 'alpha', 'size:3', Rule::unique('currencies', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'decimal_places' => ['required', 'integer', 'between:0,4'],
        ]);

        $currency = $this->editingId ? Currency::query()->findOrFail($this->editingId) : new Currency;

        // The base currency code is used across every posted document.
        if ($currency->is_base && $currency->code !== $data['code']) {
            $this->addError('code', 'The base currency code cannot be changed.');

            return;
        }

        $currency->fill($data)->save();

        $this->showForm = false;
        $this->dispatch('toast', message: "Currency {$currency->code} saved.");
    }

    public function toggleActive(int $id): void
    {
        $this->authorize('currencies.edit');

        $currency = Currency::query()->findOrFail($id);

        if ($currency->is_base) {
            $this->dispatch('toast', type: 'error', message: 'The base currency cannot be deactivated.');

            return;
        }

        $currency->update(['is_active' => ! $currency->is_active]);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'code', 'name', 'symbol', 'decimal_places');
    }

    public function render(): View
    {
        return view('core::livewire.currencies.index', [
            'currencies' => Currency::query()->orderByDesc('is_base')->orderBy('code')->get(),
        ]);
    }
}
