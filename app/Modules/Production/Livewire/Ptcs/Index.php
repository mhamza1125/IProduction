<?php

namespace App\Modules\Production\Livewire\Ptcs;

use App\Modules\Production\Models\Ptc;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('PTCs')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    #[Url(except: false)]
    public bool $late = false;

    protected function sortable(): array
    {
        return ['number', 'start_date', 'target_date', 'planned_qty'];
    }

    /** @return Builder<Ptc> */
    private function query(): Builder
    {
        $query = Ptc::query()->with(['variant.item', 'orderLine.order.customer', 'parent'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->late, fn ($q) => $q->whereNotIn('status', Ptc::FINISHED)->where('target_date', '<', now()->toDateString()));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('number', 'like', $like)
                ->orWhereHas('variant', fn ($v) => $v->where('sku', 'like', $like))
                ->orWhereHas('orderLine.order', fn ($o) => $o->where('number', 'like', $like)));
        }

        return $this->applyDateRange($query, 'start_date');
    }

    protected function exportPermission(): string
    {
        return 'ptcs.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Start' => fn (Ptc $p) => Format::date($p->start_date), 'Target' => fn (Ptc $p) => Format::date($p->target_date),
            'Item' => fn (Ptc $p) => $p->variant->fullLabel(), 'Order' => fn (Ptc $p) => $p->orderLine?->order->number, 'Planned' => 'planned_qty',
            'Released' => 'released_qty', 'Parent' => fn (Ptc $p) => $p->parent?->number, 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('production::livewire.ptcs.index', [
            'ptcs' => $this->applySorting($this->query(), 'start_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'statuses' => Ptc::STATUSES,
        ]);
    }
}
