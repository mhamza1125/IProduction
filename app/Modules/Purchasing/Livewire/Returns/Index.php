<?php

namespace App\Modules\Purchasing\Livewire\Returns;

use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Purchase returns')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'return_date', 'total'];
    }

    /** @return Builder<PurchaseReturn> */
    private function query(): Builder
    {
        $query = PurchaseReturn::query()->with('vendor')->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('reason', 'like', $like)->orWhereHas('vendor', fn ($v) => $v->where('name', 'like', $like)));
        }

        return $this->applyDateRange($query, 'return_date');
    }

    protected function exportPermission(): string
    {
        return 'purchase_returns.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Vendor' => fn (PurchaseReturn $r) => $r->vendor->name, 'Date' => fn (PurchaseReturn $r) => Format::date($r->return_date),
            'Reason' => 'reason', 'Total' => 'total', 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('purchasing::livewire.returns.index', [
            'returns' => $this->applySorting($this->query(), 'return_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
