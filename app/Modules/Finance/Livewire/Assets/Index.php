<?php

namespace App\Modules\Finance\Livewire\Assets;

use App\Modules\Finance\Models\Asset;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Asset register')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['code', 'name', 'purchase_date', 'value'];
    }

    /** @return Builder<Asset> */
    private function query(): Builder
    {
        $query = Asset::query()->with('assignedTo')->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('category', 'like', $like)
                ->orWhere('serial_number', 'like', $like)->orWhere('location', 'like', $like));
        }

        return $this->applyDateRange($query, 'purchase_date');
    }

    protected function exportPermission(): string
    {
        return 'assets.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        return ['Code' => 'code', 'Name' => 'name', 'Category' => 'category', 'Purchased' => fn (Asset $a) => Format::date($a->purchase_date), 'Value' => 'value',
            'Location' => 'location', 'Assigned to' => fn (Asset $a) => $a->assignedTo?->name, 'Serial' => 'serial_number', 'Status' => fn (Asset $a) => Asset::STATUSES[$a->status] ?? $a->status];
    }

    public function render(): View
    {
        return view('finance::livewire.assets.index', [
            'assets' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
        ]);
    }
}
