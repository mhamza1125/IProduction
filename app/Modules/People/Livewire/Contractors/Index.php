<?php

namespace App\Modules\People\Livewire\Contractors;

use App\Modules\People\Models\Contractor;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Contractors')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'active')]
    public string $status = 'active';

    protected function sortable(): array
    {
        return ['code', 'name', 'created_at'];
    }

    /** @return Builder<Contractor> */
    private function query(): Builder
    {
        return $this->applyDateRange($this->applySearch(Contractor::query(), ['code', 'name', 'contact_person', 'phone', 'cnic_ntn', 'work_types'])
            ->when($this->status !== '', fn ($q) => $q->where('is_active', $this->status === 'active')), 'created_at');
    }

    protected function exportPermission(): string
    {
        return 'contractors.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        return [
            'Code' => 'code', 'Name' => 'name', 'Contact person' => 'contact_person', 'CNIC/NTN' => 'cnic_ntn',
            'Phone' => 'phone', 'Address' => 'address',
            'Work types' => fn (Contractor $c) => implode(', ', $c->work_types ?? []),
            'Active' => fn (Contractor $c) => $c->is_active ? 'Yes' : 'No',
        ];
    }

    public function render(): View
    {
        return view('people::livewire.contractors.index', [
            'contractors' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
        ]);
    }
}
