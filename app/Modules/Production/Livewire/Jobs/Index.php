<?php

namespace App\Modules\Production\Livewire\Jobs;

use App\Modules\Production\Models\ContractorJob;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Contractor jobs')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'job_date', 'due_date'];
    }

    /** @return Builder<ContractorJob> */
    private function query(): Builder
    {
        $query = ContractorJob::query()->with(['contractor', 'lines.variant'])->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhereHas('contractor', fn ($c) => $c->where('name', 'like', $like)));
        }

        return $this->applyDateRange($query, 'job_date');
    }

    protected function exportPermission(): string
    {
        return 'contractor_jobs.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Date' => fn (ContractorJob $j) => Format::date($j->job_date), 'Due' => fn (ContractorJob $j) => Format::date($j->due_date),
            'Contractor' => fn (ContractorJob $j) => $j->contractor->name, 'Items' => fn (ContractorJob $j) => $j->lines->map(fn ($l) => $l->variant->sku.' × '.$l->quantity)->implode(', '), 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('production::livewire.jobs.index', [
            'jobs' => $this->applySorting($this->query(), 'job_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'statuses' => ContractorJob::STATUSES,
        ]);
    }
}
