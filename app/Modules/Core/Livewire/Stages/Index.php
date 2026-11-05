<?php

namespace App\Modules\Core\Livewire\Stages;

use App\Modules\Core\Models\Stage;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Production stages')]
class Index extends Component
{
    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $name = '';

    public string $description = '';

    public int $sort_order = 0;

    public bool $is_active = true;

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'description', 'is_active');
        $this->sort_order = (int) Stage::query()->max('sort_order') + 10;
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $stage = Stage::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $stage->id;
        $this->name = $stage->name;
        $this->description = (string) $stage->description;
        $this->sort_order = $stage->sort_order;
        $this->is_active = $stage->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'stages.edit' : 'stages.create');

        $data = $this->validate([
            'name' => ['required', 'string', 'max:100', Rule::unique('stages', 'name')->ignore($this->editingId)],
            'description' => ['nullable', 'string', 'max:500'],
            'sort_order' => ['required', 'integer', 'min:0'],
            'is_active' => ['boolean'],
        ]);
        $data['description'] = $data['description'] ?: null;

        ($this->editingId ? Stage::query()->findOrFail($this->editingId) : new Stage)->fill($data)->save();

        $this->showForm = false;
        $this->dispatch('toast', message: 'Stage saved.');
    }

    public function delete(int $id): void
    {
        $this->authorize('stages.delete');

        try {
            Stage::query()->findOrFail($id)->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('core::livewire.stages.index', ['stages' => Stage::query()->ordered()->get()]);
    }
}
