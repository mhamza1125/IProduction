<?php

namespace App\Modules\People\Livewire\Customers;

use App\Modules\Core\Models\Currency;
use App\Modules\People\Models\Customer;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Customer $customer = null;

    public string $code = '';

    public string $name = '';

    public string $company = '';

    public string $currency_code = '';

    public string $email = '';

    public string $phone = '';

    public string $country = '';

    public string $billing_address = '';

    public string $shipping_address = '';

    public string $ntn = '';

    public string $payment_terms = '';

    public string $notes = '';

    public bool $is_active = true;

    public function mount(?Customer $customer = null): void
    {
        if ($customer?->exists) {
            $this->customer = $customer;
            $this->fill(array_map(fn ($v) => $v ?? '', $customer->only([
                'code', 'name', 'company', 'currency_code', 'email', 'phone', 'country', 'billing_address',
                'shipping_address', 'ntn', 'payment_terms', 'notes', 'is_active',
            ])));
        }
    }

    public function copyBillingToShipping(): void
    {
        $this->shipping_address = $this->billing_address;
    }

    public function save(): void
    {
        $this->authorize($this->customer ? 'customers.edit' : 'customers.create');

        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('customers', 'code')->ignore($this->customer?->id)],
            'name' => ['required', 'string', 'max:150'],
            'company' => ['nullable', 'string', 'max:150'],
            'currency_code' => ['required', Rule::exists('currencies', 'code')->where('is_active', true)],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'country' => ['nullable', 'string', 'max:80'],
            'billing_address' => ['nullable', 'string', 'max:1000'],
            'shipping_address' => ['nullable', 'string', 'max:1000'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:150'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
        ]);

        // One currency per customer: once used, the currency is fixed.
        // A customer who needs another currency gets a separate account.
        if ($this->customer && $this->customer->currency_code !== $data['currency_code'] && $this->customer->isInUse()) {
            $this->addError('currency_code', 'This customer already has transactions in '.$this->customer->currency_code.'. Create a separate customer account for another currency.');

            return;
        }

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $customer = $this->customer ?? new Customer;
        $customer->fill($data)->save();

        session()->flash('toast', $this->customer ? 'Customer updated.' : "Customer {$customer->code} created.");
        $this->redirectRoute('people.customers.show', $customer, navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.customers.form', [
            'currencies' => Currency::query()
                ->where(fn ($q) => $q->where('is_active', true)->when($this->customer, fn ($q) => $q->orWhere('code', $this->customer?->currency_code)))
                ->orderByDesc('is_base')->orderBy('code')
                ->get()->mapWithKeys(fn (Currency $c) => [$c->code => $c->code.' · '.$c->name]),
            'currencyLocked' => $this->customer?->isInUse() ?? false,
        ])->title($this->customer ? 'Edit customer' : 'New customer');
    }
}
