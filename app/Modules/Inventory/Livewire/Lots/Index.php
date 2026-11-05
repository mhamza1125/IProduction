<?php

namespace App\Modules\Inventory\Livewire\Lots;

use App\Modules\Inventory\Models\Lot;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Lots')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $origin = '';

    protected function sortable(): array
    {
        return ['lot_number', 'received_date'];
    }

    /** @return Builder<Lot> */
    private function query(): Builder
    {
        $query = Lot::query()->with(['variant.item', 'vendor', 'contractor'])->withSum('movements as quantity_on_hand', DB::raw('qty_in - qty_out'))
            ->when($this->origin !== '', fn ($q) => $q->where('origin', $this->origin));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('lot_number', 'like', $like)
                ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', $like)->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like))));
        }

        return $this->applyDateRange($query, 'received_date');
    }

    protected function exportPermission(): string
    {
        return 'lots.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('lot_number');
    }

    protected function exportColumns(): array
    {
        return [
            'Lot' => 'lot_number',
            'SKU' => fn (Lot $l) => $l->variant->sku,
            'Item' => fn (Lot $l) => $l->variant->item->name,
            'Origin' => fn (Lot $l) => Lot::ORIGINS[$l->origin] ?? $l->origin,
            'Received' => fn (Lot $l) => Format::date($l->received_date),
            'Supplier / contractor' => fn (Lot $l) => $l->vendor->name ?? $l->contractor?->name,
            'On hand' => fn (Lot $l) => $l->getAttribute('quantity_on_hand'),
        ];
    }

    public function render(): View
    {
        return view('inventory::livewire.lots.index', [
            'lots' => $this->applySorting($this->query(), 'received_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'origins' => Lot::ORIGINS,
        ]);
    }
}
