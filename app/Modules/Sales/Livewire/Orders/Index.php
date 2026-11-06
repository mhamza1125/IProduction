<?php

namespace App\Modules\Sales\Livewire\Orders;

use App\Modules\Sales\Models\SalesOrder;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Sales orders')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $late = false;

    protected function sortable(): array
    {
        return ['number', 'order_date', 'due_date', 'total'];
    }

    /** @return Builder<SalesOrder> */
    private function query(): Builder
    {
        $query = SalesOrder::query()->with('customer')
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->late, fn ($q) => $q->whereIn('status', SalesOrder::OPEN)->where('due_date', '<', now()->toDateString()));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('customer_po_number', 'like', $like)
                ->orWhereHas('customer', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like)));
        }

        return $this->applyDateRange($query, 'order_date');
    }

    protected function exportPermission(): string
    {
        return 'sales_orders.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Date' => fn (SalesOrder $o) => Format::date($o->order_date), 'Due' => fn (SalesOrder $o) => Format::date($o->due_date),
            'Customer' => fn (SalesOrder $o) => $o->customer->name, 'Customer PO' => 'customer_po_number', 'Currency' => 'currency_code', 'Total' => 'total', 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('sales::livewire.orders.index', [
            'orders' => $this->applySorting($this->query(), 'order_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'statuses' => SalesOrder::STATUSES,
        ]);
    }
}
