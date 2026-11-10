<?php

namespace App\Modules\Inventory\Livewire\Stock;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Reservation;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use App\Support\Decimal;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Stock views from the specification:
 *  - summary: per variant, Idle (Available), In Production, Rejected, Returned, Reserved, Free
 *  - stages: per variant, Available quantity at each production stage
 *  - locations: every location / lot / stage / status bucket, including contractor-held stock
 * Filter by item type for the raw / semi-finished / finished view.
 */
#[Title('Stock')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'summary')]
    public string $view = 'summary';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $location = '';

    #[Url(except: false)]
    public bool $includeZero = false;

    protected function sortable(): array
    {
        return ['sku'];
    }

    public function updatedView(): void
    {
        $this->resetPage();
    }

    /** @return Builder<ItemVariant> */
    private function variants(): Builder
    {
        $query = ItemVariant::query()->with('item.stockUnit')
            ->when($this->type !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('type', $this->type)))
            ->when(! $this->includeZero, fn ($q) => $q->whereIn('id', StockBalance::query()->where('quantity', '>', 0)
                ->when($this->location !== '', fn ($b) => $b->where('location_id', (int) $this->location))->select('item_variant_id')));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('label', 'like', $like)
                ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like)));
        }

        return $query;
    }

    /**
     * Quantities per variant and status.
     *
     * @param  list<int>  $variantIds
     * @return array<int, array<string, string>>
     */
    private function byStatus(array $variantIds): array
    {
        $rows = StockBalance::query()->whereIn('item_variant_id', $variantIds)
            ->when($this->location !== '', fn ($q) => $q->where('location_id', (int) $this->location))
            ->groupBy('item_variant_id', 'status')
            ->selectRaw('item_variant_id, status, SUM(quantity) AS qty')->get();

        $reserved = Reservation::query()->whereIn('item_variant_id', $variantIds)->where('status', Reservation::ACTIVE)
            ->groupBy('item_variant_id')->selectRaw('item_variant_id, SUM(quantity - consumed_quantity) AS qty')->pluck('qty', 'item_variant_id');

        $out = [];
        foreach ($variantIds as $id) {
            $out[$id] = array_fill_keys(array_keys(StockStatus::LABELS), '0');
        }
        foreach ($rows as $row) {
            $out[$row->item_variant_id][$row->status] = (string) $row->getAttribute('qty');
        }
        foreach ($out as $id => $q) {
            $out[$id]['reserved'] = (string) ($reserved[$id] ?? '0');
            $out[$id]['free'] = Decimal::sub($q[StockStatus::AVAILABLE], $out[$id]['reserved'], Decimal::QUANTITY);
        }

        return $out;
    }

    /**
     * Available quantity per variant per stage (NULL stage = 0 = store).
     *
     * @param  list<int>  $variantIds
     * @return array<int, array<int, string>>
     */
    private function byStage(array $variantIds): array
    {
        $rows = StockBalance::query()->whereIn('item_variant_id', $variantIds)->where('status', StockStatus::AVAILABLE)
            ->when($this->location !== '', fn ($q) => $q->where('location_id', (int) $this->location))
            ->groupBy('item_variant_id', 'stage_id')
            ->selectRaw('item_variant_id, COALESCE(stage_id, 0) AS stage, SUM(quantity) AS qty')->get();

        $out = [];
        foreach ($rows as $row) {
            $out[$row->item_variant_id][(int) $row->getAttribute('stage')] = (string) $row->getAttribute('qty');
        }

        return $out;
    }

    /** @return Builder<StockBalance> */
    private function buckets(): Builder
    {
        $query = StockBalance::query()->with(['variant.item.stockUnit', 'stage', 'location', 'lot'])
            ->when(! $this->includeZero, fn ($q) => $q->where('quantity', '>', 0))
            ->when($this->location !== '', fn ($q) => $q->where('location_id', (int) $this->location))
            ->when($this->type !== '', fn ($q) => $q->whereHas('variant.item', fn ($i) => $i->where('type', $this->type)));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->whereHas('lot', fn ($l) => $l->where('lot_number', 'like', $like))
                ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', $like)->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like))));
        }

        return $query;
    }

    protected function exportPermission(): string
    {
        return 'stock.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->buckets()->orderBy('item_variant_id');
    }

    protected function exportColumns(): array
    {
        $columns = [
            'SKU' => fn (StockBalance $b) => $b->variant->sku,
            'Item' => fn (StockBalance $b) => $b->variant->item->name,
            'Variant' => fn (StockBalance $b) => $b->variant->label,
            'Type' => fn (StockBalance $b) => Item::TYPES[$b->variant->item->type] ?? '',
            'Stage' => fn (StockBalance $b) => $b->stage?->name,
            'Status' => fn (StockBalance $b) => StockStatus::LABELS[$b->status] ?? $b->status,
            'Location' => fn (StockBalance $b) => $b->location->name,
            'Lot' => fn (StockBalance $b) => $b->lot->lot_number,
            'Quantity' => 'quantity',
            'Unit' => fn (StockBalance $b) => $b->variant->item->stockUnit->symbol,
        ];

        if (auth()->user()?->can('cost.view')) {
            $columns['Unit cost'] = fn (StockBalance $b) => $b->lot->unit_cost;
            $columns['Value'] = fn (StockBalance $b) => $b->lot->unit_cost !== null ? Decimal::mul($b->quantity, $b->lot->unit_cost) : null;
        }

        return $columns;
    }

    public function render(): View
    {
        // The view comes from the URL; an unknown value (old link, typo) shows the summary.
        if (! in_array($this->view, ['summary', 'stages', 'locations'], true)) {
            $this->view = 'summary';
        }

        $data = [
            'types' => Item::TYPES,
            'locations' => Location::query()->orderBy('type')->orderBy('name')->pluck('name', 'id'),
            'statusLabels' => StockStatus::LABELS,
        ];

        if ($this->view === 'locations') {
            $data['buckets'] = $this->buckets()->orderBy('location_id')->orderBy('item_variant_id')->paginate($this->perPage);
        } else {
            $variants = $this->applySorting($this->variants(), 'sku')->paginate($this->perPage);
            $ids = $variants->pluck('id')->all();
            $data['variants'] = $variants;
            $data['quantities'] = $this->view === 'stages' ? $this->byStage($ids) : $this->byStatus($ids);
            $data['stages'] = $this->view === 'stages'
                ? Stage::query()->whereIn('id', StockBalance::query()->whereIn('item_variant_id', $ids)->whereNotNull('stage_id')->select('stage_id'))->ordered()->get()
                : collect();
        }

        return view('inventory::livewire.stock.index', $data);
    }
}
