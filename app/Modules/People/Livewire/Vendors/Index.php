<?php

namespace App\Modules\People\Livewire\Vendors;

use App\Modules\People\Models\Vendor;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Vendors')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'active')]
    public string $status = 'active';

    protected function sortable(): array
    {
        return ['code', 'name', 'created_at'];
    }

    /** @return Builder<Vendor> */
    private function query(): Builder
    {
        return $this->applyDateRange($this->applySearch(Vendor::query(), ['code', 'name', 'contact_person', 'phone', 'email', 'ntn'])
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active')), 'created_at');
    }

    protected function exportPermission(): string
    {
        return 'vendors.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        return [
            'Code' => 'code', 'Name' => 'name', 'Contact person' => 'contact_person', 'Phone' => 'phone',
            'Email' => 'email', 'Address' => 'address', 'NTN' => 'ntn', 'Payment terms' => 'payment_terms',
            'Over-receipt %' => 'over_receipt_percent', 'Active' => fn (Vendor $v) => $v->is_active ? 'Yes' : 'No',
        ];
    }

    public function render(): View
    {
        return view('people::livewire.vendors.index', [
            'vendors' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
        ]);
    }
}
