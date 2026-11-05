<?php

namespace App\Modules\People\Livewire\Customers;

use App\Modules\People\Models\Customer;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Customers')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'active')]
    public string $status = 'active';

    #[Url(except: '')]
    public string $currency = '';

    protected function sortable(): array
    {
        return ['code', 'name', 'country', 'currency_code', 'created_at'];
    }

    /** @return Builder<Customer> */
    private function query(): Builder
    {
        return $this->applyDateRange($this->applySearch(Customer::query(), ['code', 'name', 'company', 'email', 'phone', 'country'])
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active'))
            ->when($this->currency !== '', fn ($q) => $q->where('currency_code', $this->currency)), 'created_at');
    }

    protected function exportPermission(): string
    {
        return 'customers.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        return [
            'Code' => 'code', 'Name' => 'name', 'Company' => 'company', 'Currency' => 'currency_code',
            'Email' => 'email', 'Phone' => 'phone', 'Country' => 'country', 'NTN' => 'ntn',
            'Payment terms' => 'payment_terms', 'Billing address' => 'billing_address', 'Shipping address' => 'shipping_address',
            'Active' => fn (Customer $c) => $c->is_active ? 'Yes' : 'No',
        ];
    }

    public function render(): View
    {
        return view('people::livewire.customers.index', [
            'customers' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
            'currencies' => Customer::query()->distinct()->orderBy('currency_code')->pluck('currency_code'),
        ]);
    }
}
