<?php

namespace App\Modules\Production\Livewire\Processes;

use App\Modules\Production\Models\MaterialProcess;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Material processing')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'process_date'];
    }

    /** @return Builder<MaterialProcess> */
    private function query(): Builder
    {
        $query = MaterialProcess::query()->with(['employee', 'contractor'])->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('process_name', 'like', $like));
        }

        return $this->applyDateRange($query, 'process_date');
    }

    protected function exportPermission(): string
    {
        return 'material_processes.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Date' => fn (MaterialProcess $p) => Format::date($p->process_date), 'Process' => 'process_name',
            'Worker' => fn (MaterialProcess $p) => $p->employee->name ?? $p->contractor?->name, 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('production::livewire.processes.index', [
            'processes' => $this->applySorting($this->query(), 'process_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
