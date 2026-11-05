<?php

namespace App\Modules\Catalog\Livewire\Variants;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Variants')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $type = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    protected function sortable(): array
    {
        return ['sku', 'label'];
    }

    /** @return Builder<ItemVariant> */
    private function query(): Builder
    {
        $query = ItemVariant::query()->with('item')
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->type !== '', fn ($q) => $q->whereHas('item', fn ($i) => $i->where('type', $this->type)));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('sku', 'like', $like)->orWhere('barcode', 'like', $like)->orWhere('label', 'like', $like)
                ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like)));
        }

        return $query;
    }

    protected function exportPermission(): string
    {
        return 'items.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('sku');
    }

    protected function exportColumns(): array
    {
        return [
            'SKU' => 'sku', 'Barcode' => 'barcode', 'Item code' => fn (ItemVariant $v) => $v->item->code,
            'Item' => fn (ItemVariant $v) => $v->item->name, 'Variant' => 'label',
            'Type' => fn (ItemVariant $v) => Item::TYPES[$v->item->type] ?? '',
            'Price' => fn (ItemVariant $v) => $v->sellingPrice(), 'Min stock' => 'min_stock',
            'Active' => fn (ItemVariant $v) => $v->is_active ? 'Yes' : 'No',
        ];
    }

    public function render(): View
    {
        return view('catalog::livewire.variants.index', [
            'variants' => $this->applySorting($this->query(), 'sku')->paginate($this->perPage),
            'types' => Item::TYPES,
        ]);
    }
}
