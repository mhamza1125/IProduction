<?php

namespace App\Modules\Catalog\Livewire\Routes;

use App\Modules\Catalog\Models\ProductionRoute;
use App\Support\Livewire\WithDataTable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Routes')]
class Index extends Component
{
    use WithDataTable;

    protected function sortable(): array
    {
        return ['name'];
    }

    public function render(): View
    {
        $query = ProductionRoute::query()->with(['item', 'stages.stage'])->withCount('stages');
        $term = trim($this->search);

        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('name', 'like', $like)->orWhereHas('item', fn ($i) => $i->where('code', 'like', $like)->orWhere('name', 'like', $like)));
        }

        return view('catalog::livewire.routes.index', [
            'routes' => $this->applySorting($query, 'name')->paginate($this->perPage),
        ]);
    }
}
