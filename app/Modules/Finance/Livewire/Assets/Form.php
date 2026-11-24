<?php

namespace App\Modules\Finance\Livewire\Assets;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\Asset;
use App\Modules\Finance\Services\Depreciation;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Asset $asset = null;

    public string $code = '';

    public string $name = '';

    public string $category = '';

    public ?string $purchase_date = null;

    public string $value = '';

    public string $location = '';

    public ?int $assigned_employee_id = null;

    public string $serial_number = '';

    public string $status = 'in_use';

    public string $notes = '';

    public string $useful_life_months = '';

    public string $salvage_value = '';

    public ?string $depreciation_start = null;

    public function mount(?Asset $asset = null): void
    {
        if ($asset?->exists) {
            $this->asset = $asset;
            foreach (['code', 'name', 'category', 'value', 'location', 'serial_number', 'status', 'notes'] as $f) {
                $this->{$f} = (string) $asset->{$f};
            }
            $this->assigned_employee_id = $asset->assigned_employee_id;
            $this->purchase_date = $asset->purchase_date?->format('Y-m-d');
            $this->useful_life_months = (string) $asset->useful_life_months;
            $this->salvage_value = (string) $asset->salvage_value;
            $this->depreciation_start = $asset->depreciation_start?->format('Y-m-d');
        }
    }

    public function save(DocumentNumber $numbers): void
    {
        $this->authorize($this->asset ? 'assets.edit' : 'assets.create');
        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('assets', 'code')->ignore($this->asset?->id)],
            'name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'purchase_date' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'], 'location' => ['nullable', 'string', 'max:150'], 'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'serial_number' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::in(array_keys(Asset::STATUSES))], 'notes' => ['nullable', 'string'],
            'useful_life_months' => ['nullable', 'integer', 'min:1', 'max:1200'], 'salvage_value' => ['nullable', 'numeric', 'min:0'],
            'depreciation_start' => ['nullable', 'date', 'required_with:useful_life_months'],
        ], ['depreciation_start.required_with' => 'Give the month depreciation starts.']);
        if ($this->useful_life_months !== '' && $this->value === '') {
            $this->addError('value', 'Enter the cost to depreciate the asset.');

            return;
        }
        try {
            Depreciation::assertValid($this->value === '' ? null : (string) $this->value, $this->salvage_value === '' ? null : (string) $this->salvage_value);
        } catch (DocumentException $e) {
            $this->addError('salvage_value', $e->getMessage());

            return;
        }
        if ($this->asset && $this->asset->depreciations()->exists() && ((string) $this->asset->value !== Decimal::round((string) $this->value) || (int) $this->asset->useful_life_months !== (int) $this->useful_life_months)) {
            $this->addError('value', 'This asset has posted depreciation; its cost and useful life can no longer change. Record a revaluation with a journal voucher.');

            return;
        }
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $asset = DB::transaction(function () use ($data, $numbers) {
            $data['code'] ??= $this->asset->code ?? $numbers->next('asset');

            $asset = $this->asset ?? new Asset;
            $asset->fill($data)->save();

            return $asset;
        });
        session()->flash('toast', "{$asset->code} saved.");
        $this->redirectRoute('finance.assets.edit', $asset, navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('assets.delete');
        $this->asset?->delete();
        session()->flash('toast', 'Asset removed from the register.');
        $this->redirectRoute('finance.assets.index', navigate: true);
    }

    public function render(): View
    {
        $depreciation = app(Depreciation::class);

        return view('finance::livewire.assets.form', [
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'monthly' => $this->asset ? $depreciation->monthly($this->asset) : null,
            'accumulated' => $this->asset ? $depreciation->accumulated($this->asset) : null,
            'bookValue' => $this->asset ? $depreciation->bookValue($this->asset) : null,
            'history' => $this->asset ? $this->asset->depreciations()->with('journal')->get() : collect(),
        ])->title($this->asset ? $this->asset->code.' '.$this->asset->name : 'New asset');
    }
}
