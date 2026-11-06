<?php

namespace App\Modules\Purchasing\Livewire\Receipts;

use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Goods receipts')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'receipt_date'];
    }

    /** @return Builder<GoodsReceipt> */
    private function query(): Builder
    {
        $query = GoodsReceipt::query()->with(['vendor', 'order'])->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('vendor_delivery_note', 'like', $like)
                ->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like))->orWhereHas('order', fn ($o) => $o->where('number', 'like', $like)));
        }

        return $this->applyDateRange($query, 'receipt_date');
    }

    protected function exportPermission(): string
    {
        return 'goods_receipts.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Date' => fn (GoodsReceipt $r) => Format::date($r->receipt_date), 'PO' => fn (GoodsReceipt $r) => $r->order->number,
            'Vendor' => fn (GoodsReceipt $r) => $r->vendor->name, 'Delivery note' => 'vendor_delivery_note', 'Value' => 'total_value', 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('purchasing::livewire.receipts.index', [
            'receipts' => $this->applySorting($this->query(), 'receipt_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
