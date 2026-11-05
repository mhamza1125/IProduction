<?php

namespace App\Modules\Catalog\Livewire\Items;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Services\BomService;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Item $item;

    public function mount(Item $item): void
    {
        $this->item = $item;
    }

    public function toggleActive(): void
    {
        $this->authorize('items.edit');
        $this->item->update(['is_active' => ! $this->item->is_active]);
    }

    public function delete(): void
    {
        $this->authorize('items.delete');

        try {
            $this->item->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        session()->flash('toast', 'Item deleted.');
        $this->redirectRoute('catalog.items.index', navigate: true);
    }

    public function newBomVersion(BomService $boms): void
    {
        $this->authorize('boms.create');

        try {
            $bom = $boms->newVersion($this->item);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->redirectRoute('catalog.boms.edit', $bom, navigate: true);
    }

    public function render(): View
    {
        $this->item->loadMissing(['category', 'stockUnit', 'purchaseUnit', 'salesUnit']);

        return view('catalog::livewire.items.show', [
            'routes' => $this->item->routes()->withCount('stages')->orderByDesc('is_default')->get(),
            'boms' => $this->item->boms()->orderByDesc('version')->get(),
            'canSeeCost' => auth()->user()?->can('cost.view') ?? false,
        ])->title($this->item->name);
    }
}
