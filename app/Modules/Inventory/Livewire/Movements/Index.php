<?php

namespace App\Modules\Inventory\Livewire\Movements;

use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Models\StockStatus;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Stock movements')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: '')]
    public string $location = '';

    protected function sortable(): array
    {
        return ['movement_date', 'id'];
    }

    /** @return Builder<StockMovement> */
    private function query(): Builder
    {
        $query = StockMovement::query()->with(['variant.item.stockUnit', 'stage', 'location', 'lot', 'source', 'user'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->location !== '', fn ($q) => $q->where('location_id', (int) $this->location));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->whereHas('lot', fn ($l) => $l->where('lot_number', 'like', $like))
                ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', $like)->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like))));
        }

        return $this->applyDateRange($query, 'movement_date');
    }

    protected function exportPermission(): string
    {
        return 'stock_movements.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('movement_date')->orderBy('id');
    }

    protected function exportColumns(): array
    {
        $columns = [
            'Date' => fn (StockMovement $m) => Format::date($m->movement_date),
            'Document' => fn (StockMovement $m) => self::documentLabel($m),
            'SKU' => fn (StockMovement $m) => $m->variant->sku,
            'Item' => fn (StockMovement $m) => $m->variant->item->name,
            'Lot' => fn (StockMovement $m) => $m->lot->lot_number,
            'Stage' => fn (StockMovement $m) => $m->stage?->name,
            'Status' => fn (StockMovement $m) => StockStatus::LABELS[$m->status] ?? $m->status,
            'Location' => fn (StockMovement $m) => $m->location->name,
            'In' => 'qty_in',
            'Out' => 'qty_out',
            'User' => fn (StockMovement $m) => $m->user?->name,
        ];

        if (auth()->user()?->can('cost.view')) {
            $columns['Unit cost'] = 'unit_cost';
            $columns['Total cost'] = 'total_cost';
        }

        return $columns;
    }

    public static function documentLabel(StockMovement $m): string
    {
        $source = $m->source;

        return $source ? (string) ($source->getAttribute('number') ?? class_basename($source).' #'.$source->getKey()) : '';
    }

    public function render(): View
    {
        return view('inventory::livewire.movements.index', [
            'movements' => $this->applySorting($this->query(), 'movement_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'statuses' => StockStatus::LABELS,
            'locations' => Location::query()->orderBy('name')->pluck('name', 'id'),
        ]);
    }
}
