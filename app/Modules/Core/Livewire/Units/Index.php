<?php

namespace App\Modules\Core\Livewire\Units;

use App\Modules\Core\Models\Unit;
use App\Modules\Core\Models\UnitConversion;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Units')]
class Index extends Component
{
    public bool $showUnit = false;

    #[Locked]
    public ?int $unitId = null;

    public string $name = '';

    public string $symbol = '';

    public bool $allows_decimal = true;

    public bool $is_active = true;

    public bool $showConversion = false;

    #[Locked]
    public ?int $conversionId = null;

    public ?int $from_unit_id = null;

    public ?int $to_unit_id = null;

    public string $factor = '';

    public function createUnit(): void
    {
        $this->resetValidation();
        $this->reset('unitId', 'name', 'symbol', 'allows_decimal', 'is_active');
        $this->showUnit = true;
    }

    public function editUnit(int $id): void
    {
        $unit = Unit::query()->findOrFail($id);
        $this->resetValidation();
        $this->unitId = $unit->id;
        $this->name = $unit->name;
        $this->symbol = $unit->symbol;
        $this->allows_decimal = $unit->allows_decimal;
        $this->is_active = $unit->is_active;
        $this->showUnit = true;
    }

    public function saveUnit(): void
    {
        $this->authorize($this->unitId ? 'units.edit' : 'units.create');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:50'],
            'symbol' => ['required', 'string', 'max:15', Rule::unique('units', 'symbol')->ignore($this->unitId)],
            'allows_decimal' => ['boolean'],
            'is_active' => ['boolean'],
        ]);

        ($this->unitId ? Unit::query()->findOrFail($this->unitId) : new Unit)->fill($data)->save();

        $this->showUnit = false;
        $this->dispatch('toast', message: 'Unit saved.');
    }

    public function deleteUnit(int $id): void
    {
        $this->authorize('units.delete');

        try {
            Unit::query()->findOrFail($id)->deleteIfUnused();
            $this->dispatch('toast', message: 'Unit deleted.');
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function createConversion(): void
    {
        $this->resetValidation();
        $this->reset('conversionId', 'from_unit_id', 'to_unit_id', 'factor');
        $this->showConversion = true;
    }

    public function editConversion(int $id): void
    {
        $conversion = UnitConversion::query()->whereNull('item_id')->findOrFail($id);
        $this->resetValidation();
        $this->conversionId = $conversion->id;
        $this->from_unit_id = $conversion->from_unit_id;
        $this->to_unit_id = $conversion->to_unit_id;
        $this->factor = rtrim(rtrim($conversion->factor, '0'), '.');
        $this->showConversion = true;
    }

    public function saveConversion(): void
    {
        $this->authorize('units.edit');

        $data = $this->validate([
            'from_unit_id' => ['required', Rule::exists('units', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'to_unit_id' => ['required', 'different:from_unit_id', Rule::exists('units', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'factor' => ['required', 'numeric', 'gt:0', 'decimal:0,6'],
        ]);

        $duplicate = UnitConversion::query()
            ->whereNull('item_id')
            ->where('from_unit_id', $data['from_unit_id'])
            ->where('to_unit_id', $data['to_unit_id'])
            ->when($this->conversionId, fn ($q) => $q->whereKeyNot($this->conversionId))
            ->exists();

        if ($duplicate) {
            $this->addError('to_unit_id', 'This conversion already exists.');

            return;
        }

        ($this->conversionId ? UnitConversion::query()->findOrFail($this->conversionId) : new UnitConversion)
            ->fill($data + ['item_id' => null])->save();

        $this->showConversion = false;
        $this->dispatch('toast', message: 'Conversion saved.');
    }

    public function deleteConversion(int $id): void
    {
        $this->authorize('units.edit');
        UnitConversion::query()->whereNull('item_id')->findOrFail($id)->delete();
    }

    public function render(): View
    {
        return view('core::livewire.units.index', [
            'units' => Unit::query()->orderBy('name')->get(),
            'conversions' => UnitConversion::query()->whereNull('item_id')->with(['fromUnit', 'toUnit'])->get(),
            'unitOptions' => Unit::query()->active()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
