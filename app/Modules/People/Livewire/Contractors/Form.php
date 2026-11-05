<?php

namespace App\Modules\People\Livewire\Contractors;

use App\Modules\Core\Models\Stage;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Vendor;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Contractor $contractor = null;

    public string $code = '';

    public string $name = '';

    public string $contact_person = '';

    public string $cnic_ntn = '';

    public string $phone = '';

    public string $address = '';

    /** @var list<string> */
    public array $work_types = [];

    public string $notes = '';

    public bool $is_active = true;

    /** Same business as this vendor (shares the party). */
    public ?int $linked_vendor_id = null;

    public function mount(?Contractor $contractor = null): void
    {
        if ($contractor?->exists) {
            $this->contractor = $contractor;
            $this->fill(array_map(fn ($v) => $v ?? '', $contractor->only([
                'code', 'name', 'contact_person', 'cnic_ntn', 'phone', 'address', 'notes', 'is_active',
            ])));
            $this->work_types = $contractor->work_types ?? [];
            $this->linked_vendor_id = Vendor::query()->where('party_id', $contractor->party_id)->value('id');
        }
    }

    public function save(): void
    {
        $this->authorize($this->contractor ? 'contractors.edit' : 'contractors.create');

        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('contractors', 'code')->ignore($this->contractor?->id)],
            'name' => ['required', 'string', 'max:150'],
            'contact_person' => ['nullable', 'string', 'max:150'],
            'cnic_ntn' => ['nullable', 'string', 'max:30'],
            'phone' => ['nullable', 'string', 'max:50'],
            'address' => ['nullable', 'string', 'max:1000'],
            'work_types' => ['array'],
            'work_types.*' => ['string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_active' => ['boolean'],
            'linked_vendor_id' => ['nullable', Rule::exists('vendors', 'id')->whereNull('deleted_at')],
        ]);

        $link = $data['linked_vendor_id'];
        unset($data['linked_vendor_id']);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        $data['work_types'] = array_values(array_unique($data['work_types']));

        $contractor = $this->contractor ?? new Contractor;

        if (! $contractor->exists && $link) {
            $data['party_id'] = Vendor::query()->findOrFail($link)->party_id;
        }

        $contractor->fill($data)->save();

        session()->flash('toast', $this->contractor ? 'Contractor updated.' : "Contractor {$contractor->code} created.");
        $this->redirectRoute('people.contractors.show', $contractor, navigate: true);
    }

    public function render(): View
    {
        return view('people::livewire.contractors.form', [
            'vendors' => Vendor::query()->active()->orderBy('name')->get()->mapWithKeys(fn (Vendor $v) => [$v->id => $v->label()]),
            'stages' => Stage::query()->active()->ordered()->pluck('name'),
        ])->title($this->contractor ? 'Edit contractor' : 'New contractor');
    }
}
