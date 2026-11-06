<?php

namespace App\Modules\Purchasing\Livewire\Bills;

use App\Modules\Purchasing\Models\VendorBill;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Vendor bills')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'bill_date', 'due_date', 'total'];
    }

    /** @return Builder<VendorBill> */
    private function query(): Builder
    {
        $query = VendorBill::query()->with('vendor')->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('vendor_invoice_number', 'like', $like)->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like)));
        }

        return $this->applyDateRange($query, 'bill_date');
    }

    protected function exportPermission(): string
    {
        return 'vendor_bills.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Vendor invoice' => 'vendor_invoice_number', 'Vendor' => fn (VendorBill $b) => $b->vendor->name,
            'Date' => fn (VendorBill $b) => Format::date($b->bill_date), 'Due' => fn (VendorBill $b) => Format::date($b->due_date),
            'Total' => 'total', 'Paid' => 'paid_amount', 'Balance' => fn (VendorBill $b) => $b->balance(), 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('purchasing::livewire.bills.index', [
            'bills' => $this->applySorting($this->query(), 'bill_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
