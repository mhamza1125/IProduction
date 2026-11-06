<?php

namespace App\Modules\Purchasing\Livewire\Orders;

use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Purchase orders')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'order_date', 'total'];
    }

    /** @return Builder<PurchaseOrder> */
    private function query(): Builder
    {
        $query = PurchaseOrder::query()->with('vendor')->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like)->orWhere('code', 'like', $like)));
        }

        return $this->applyDateRange($query, 'order_date');
    }

    protected function exportPermission(): string
    {
        return 'purchase_orders.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return [
            'Number' => 'number', 'Date' => fn (PurchaseOrder $o) => Format::date($o->order_date), 'Vendor' => fn (PurchaseOrder $o) => $o->vendor->name,
            'Expected' => fn (PurchaseOrder $o) => Format::date($o->expected_date), 'Total' => 'total', 'Status' => 'status',
        ];
    }

    public function render(): View
    {
        return view('purchasing::livewire.orders.index', [
            'orders' => $this->applySorting($this->query(), 'order_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'statuses' => PurchaseOrder::STATUSES,
        ]);
    }
}
