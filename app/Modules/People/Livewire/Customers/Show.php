<?php

namespace App\Modules\People\Livewire\Customers;

use App\Modules\People\Models\Customer;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Customer $customer;

    public function mount(Customer $customer): void
    {
        $this->customer = $customer;
    }

    public function toggleActive(): void
    {
        $this->authorize('customers.edit');
        $this->customer->update(['is_active' => ! $this->customer->is_active]);
    }

    public function delete(): void
    {
        $this->authorize('customers.delete');

        try {
            $this->customer->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        session()->flash('toast', 'Customer deleted.');
        $this->redirectRoute('people.customers.index', navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.customers.show')->title($this->customer->name);
    }
}
