<?php

namespace App\Modules\Catalog\Livewire\Boms;

use App\Modules\Catalog\Models\Bom;
use App\Support\Livewire\WithDataTable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('BOMs')]
class Index extends Component
{
    use WithDataTable;

    #[Url(except: 'current')]
    public string $status = 'current';

    protected function sortable(): array
    {
        return ['effective_date', 'version'];
    }

    public function render(): View
    {
        $query = Bom::query()->with('item')->withCount('lines')
            ->when($this->status === 'current', fn ($q) => $q->where(fn ($q) => $q->where('is_active', true)->orWhere('status', Bom::DRAFT)))
            ->when(in_array($this->status, [Bom::DRAFT, Bom::APPROVED, Bom::SUPERSEDED], true), fn ($q) => $q->where('status', $this->status));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->whereHas('item', fn ($i) => $i->where('code', 'like', $like)->orWhere('name', 'like', $like));
        }

        return view('catalog::livewire.boms.index', [
            'boms' => $this->applySorting($query, 'effective_date', 'desc')->paginate($this->perPage),
        ]);
    }
}
