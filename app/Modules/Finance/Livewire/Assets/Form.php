<?php

namespace App\Modules\Finance\Livewire\Assets;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\Asset;
use App\Modules\People\Models\Employee;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Asset $asset = null;

    public string $code = '';

    public string $name = '';

    public string $category = '';

    public ?string $purchase_date = null;

    public string $value = '';

    public string $location = '';

    public ?int $assigned_employee_id = null;

    public string $serial_number = '';

    public string $status = 'in_use';

    public string $notes = '';

    public function mount(?Asset $asset = null): void
    {
        if ($asset?->exists) {
            $this->asset = $asset;
            foreach (['code', 'name', 'category', 'value', 'location', 'serial_number', 'status', 'notes'] as $f) {
                $this->{$f} = (string) $asset->{$f};
            }
            $this->assigned_employee_id = $asset->assigned_employee_id;
            $this->purchase_date = $asset->purchase_date?->format('Y-m-d');
        }
    }

    public function save(DocumentNumber $numbers): void
    {
        $this->authorize($this->asset ? 'assets.edit' : 'assets.create');
        $data = $this->validate([
            'code' => ['nullable', 'string', 'max:30', Rule::unique('assets', 'code')->ignore($this->asset?->id)],
            'name' => ['required', 'string', 'max:150'], 'category' => ['nullable', 'string', 'max:100'], 'purchase_date' => ['nullable', 'date'],
            'value' => ['nullable', 'numeric', 'min:0'], 'location' => ['nullable', 'string', 'max:150'], 'assigned_employee_id' => ['nullable', 'exists:employees,id'],
            'serial_number' => ['nullable', 'string', 'max:100'], 'status' => ['required', Rule::in(array_keys(Asset::STATUSES))], 'notes' => ['nullable', 'string'],
        ]);
        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);

        $asset = DB::transaction(function () use ($data, $numbers) {
            $data['code'] ??= $this->asset->code ?? $numbers->next('asset');

            $asset = $this->asset ?? new Asset;
            $asset->fill($data)->save();

            return $asset;
        });
        session()->flash('toast', "{$asset->code} saved.");
        $this->redirectRoute('finance.assets.edit', $asset, navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('assets.delete');
        $this->asset?->delete();
        session()->flash('toast', 'Asset removed from the register.');
        $this->redirectRoute('finance.assets.index', navigate: true);
    }

    public function render(): View
    {
        return view('finance::livewire.assets.form', [
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ])->title($this->asset ? $this->asset->code.' '.$this->asset->name : 'New asset');
    }
}
