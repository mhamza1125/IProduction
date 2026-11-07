<?php

namespace App\Modules\Production\Livewire\Rejections;

use App\Modules\Production\Models\Rejection;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Rejections')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: 'open')]
    public string $status = 'open';

    protected function sortable(): array
    {
        return ['number', 'rejection_date', 'quantity'];
    }

    /** @return Builder<Rejection> */
    private function query(): Builder
    {
        $query = Rejection::query()->with(['variant', 'stage', 'ptc', 'job', 'responsible', 'lot'])->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)->orWhere('reason', 'like', $like)->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', $like)));
        }

        return $this->applyDateRange($query, 'rejection_date');
    }

    protected function exportPermission(): string
    {
        return 'rejections.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Date' => fn (Rejection $r) => Format::date($r->rejection_date), 'Item' => fn (Rejection $r) => $r->variant->sku,
            'Stage' => fn (Rejection $r) => $r->stage?->name, 'Quantity' => 'quantity', 'Decided' => 'decided_qty', 'Reason' => 'reason',
            'Source' => fn (Rejection $r) => $r->ptc->number ?? $r->job?->number, 'Responsible' => fn (Rejection $r) => $r->responsible?->getAttribute('name'), 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('production::livewire.rejections.index', [
            'rejections' => $this->applySorting($this->query(), 'rejection_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
