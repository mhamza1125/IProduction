<?php

namespace App\Modules\Catalog\Livewire\Items;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Lookup;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Items')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: '')]
    public string $category = '';

    protected function sortable(): array
    {
        return ['code', 'name', 'type', 'created_at'];
    }

    /** @return Builder<Item> */
    private function query(): Builder
    {
        return $this->applySearch(Item::query()->with(['category', 'stockUnit'])->withCount('variants'), ['code', 'name', 'description'])
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->type !== '', fn ($q) => $q->where('type', $this->type))
            ->when($this->category !== '', fn ($q) => $q->where('category_id', (int) $this->category));
    }

    protected function exportPermission(): string
    {
        return 'items.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        $columns = [
            'Code' => 'code', 'Name' => 'name',
            'Type' => fn (Item $i) => Item::TYPES[$i->type] ?? $i->type,
            'Category' => fn (Item $i) => $i->category?->name,
            'Stock unit' => fn (Item $i) => $i->stockUnit->symbol,
            'Selling price' => 'default_selling_price',
            'Min stock' => 'min_stock',
            'Buy' => fn (Item $i) => $i->can_buy ? 'Yes' : 'No',
            'Make' => fn (Item $i) => $i->can_make ? 'Yes' : 'No',
            'Sell' => fn (Item $i) => $i->can_sell ? 'Yes' : 'No',
            'Variants' => 'variants_count',
            'Active' => fn (Item $i) => $i->is_active ? 'Yes' : 'No',
        ];

        if (auth()->user()?->can('cost.view')) {
            $columns['Purchase cost'] = 'default_purchase_cost';
        }

        return $columns;
    }

    public function render(): View
    {
        return view('catalog::livewire.items.index', [
            'items' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
            'types' => Item::TYPES,
            'categories' => Lookup::options('item_category'),
        ]);
    }
}
