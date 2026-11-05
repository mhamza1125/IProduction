<?php

namespace App\Modules\People\Livewire\Vendors;

use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Vendor;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Vendor $vendor;

    public function mount(Vendor $vendor): void
    {
        $this->vendor = $vendor;
    }

    public function toggleActive(): void
    {
        $this->authorize('vendors.edit');
        $this->vendor->update(['is_active' => ! $this->vendor->is_active]);
    }

    public function delete(): void
    {
        $this->authorize('vendors.delete');

        try {
            $this->vendor->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        session()->flash('toast', 'Vendor deleted.');
        $this->redirectRoute('people.vendors.index', navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.vendors.show', [
            'linkedContractor' => Contractor::query()->where('party_id', $this->vendor->party_id)->first(),
        ])->title($this->vendor->name);
    }
}
