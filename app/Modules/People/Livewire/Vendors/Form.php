<?php

namespace App\Modules\People\Livewire\Vendors;

use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Vendor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Vendor $vendor = null;

    public string $code = '';

    public string $name = '';

    public string $contact_person = '';

    public string $phone = '';

    public string $email = '';

    public string $address = '';

    public string $ntn = '';

    public string $payment_terms = '';

    public string $over_receipt_percent = '';

    public string $notes = '';

    public bool $is_active = true;

    /** Same business as this contractor (shares the party, so both ledgers can be seen together). */
    public ?int $linked_contractor_id = null;

    public function mount(?Vendor $vendor = null): void
    {
        if ($vendor?->exists) {
            $this->vendor = $vendor;
            $this->fill(array_map(fn ($v) => $v ?? '', $vendor->only([
                'code', 'name', 'contact_person', 'phone', 'email', 'address', 'ntn', 'payment_terms', 'notes', 'is_active',
            ])));
            $this->over_receipt_percent = (string) $vendor->over_receipt_percent;
            $this->linked_contractor_id = Contractor::query()->where('party_id', $vendor->party_id)->value('id');
        }
    }

    public function save(): void
    {
        $this->authorize($this->vendor ? 'vendors.edit' : 'vendors.create');

        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('vendors', 'code')->ignore($this->vendor?->id)],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'],
            'address' => ['nullable', 'string', 'max:1000'],
            'ntn' => ['nullable', 'string', 'max:30'],
            'payment_terms' => ['nullable', 'string', 'max:150'],
            'over_receipt_percent' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'linked_contractor_id' => ['nullable', Rule::exists('contractors', 'id')->whereNull('deleted_at')],
        ]);

        $link = $data['linked_contractor_id'];
        unset($data['linked_contractor_id']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $vendor = $this->vendor ?? new Vendor;

        if (! $vendor->exists && $link) {
            $data['party_id'] = Contractor::query()->findOrFail($link)->party_id;
        }

        $vendor->fill($data)->save();

        session()->flash('toast', $this->vendor ? 'Vendor updated.' : "Vendor {$vendor->code} created.");
        $this->redirectRoute('people.vendors.show', $vendor, navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.vendors.form', [
            'contractors' => Contractor::query()->active()->orderBy('name')->get()->mapWithKeys(fn (Contractor $c) => [$c->id => $c->label()]),
        ])->title($this->vendor ? 'Edit vendor' : 'New vendor');
    }
}
