<?php

namespace App\Modules\Core\Livewire\Lookups;

use App\Modules\Core\Models\Lookup;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Lookups')]
class Index extends Component
{
    #[Url]
    public string $type = 'item_category';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function mount(): void
    {
        if (! array_key_exists($this->type, Lookup::TYPES)) {
            $this->type = 'item_category';
        }
    }

    public function updatedType(): void
    {
        if (! array_key_exists($this->type, Lookup::TYPES)) {
            $this->type = 'item_category';
        }
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'sort_order', 'is_active');
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $lookup = Lookup::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $lookup->id;
        $this->name = $lookup->name;
        $this->sort_order = $lookup->sort_order;
        $this->is_active = $lookup->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'lookups.edit' : 'lookups.create');

        $data = $this->validate([
            'type' => ['required', Rule::in(array_keys(Lookup::TYPES))],
            'name' => ['required', 'string', 'max:100', Rule::unique('lookups', 'name')->where('type', $this->type)->ignore($this->editingId)],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);

        ($this->editingId ? Lookup::query()->findOrFail($this->editingId) : new Lookup)->fill($data)->save();

        $this->showForm = false;
        $this->dispatch('toast', message: 'Saved.');
    }

    public function delete(int $id): void
    {
        $this->authorize('lookups.delete');

        try {
            Lookup::query()->findOrFail($id)->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('core::livewire.lookups.index', [
            'types' => Lookup::TYPES,
            'lookups' => Lookup::query()->ofType($this->type)->get(),
        ]);
    }
}
