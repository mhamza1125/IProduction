<?php

namespace App\Modules\People\Livewire\Contractors;

use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Vendor;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Contractor $contractor;

    public function mount(Contractor $contractor): void
    {
        $this->contractor = $contractor;
    }

    public function toggleActive(): void
    {
        $this->authorize('contractors.edit');
        $this->contractor->update(['is_active' => ! $this->contractor->is_active]);
    }

    public function delete(): void
    {
        $this->authorize('contractors.delete');

        try {
            $this->contractor->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        session()->flash('toast', 'Contractor deleted.');
        $this->redirectRoute('people.contractors.index', navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.contractors.show', [
            'linkedVendor' => Vendor::query()->where('party_id', $this->contractor->party_id)->first(),
        ])->title($this->contractor->name);
    }
}
