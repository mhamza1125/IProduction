<?php

namespace App\Modules\Inventory\Livewire\Locations;

use App\Modules\Inventory\Models\Location;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Store locations. Main Store, Production Floor and one location per
 * contractor are created by the system; users add more stores.
 */
#[Title('Locations')]
class Index extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'code', 'name', 'is_active');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $location = Location::query()->where('type', Location::STORE)->whereNull('system_key')->findOrFail($id);
        $this->editingId = $location->id;
        $this->code = $location->code;
        $this->name = $location->name;
        $this->is_active = $location->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'locations.edit' : 'locations.create');

        $data = $this->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('locations', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:100'],
            'is_active' => ['boolean'],
        ]);

        $location = $this->editingId
            ? Location::query()->where('type', Location::STORE)->whereNull('system_key')->findOrFail($this->editingId)
            : new Location(['type' => Location::STORE]);
        $location->fill($data)->save();

        $this->showForm = false;
        $this->dispatch('toast', message: 'Location saved.');
    }

    public function render(): View
    {
        return view('inventory::livewire.locations.index', [
            'locations' => Location::query()->with('contractor')->orderByRaw("FIELD(type, 'store', 'production', 'contractor')")->orderBy('name')->get(),
            'types' => Location::TYPES,
        ]);
    }
}
