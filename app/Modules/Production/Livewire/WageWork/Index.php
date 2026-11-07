<?php

namespace App\Modules\Production\Livewire\WageWork;

use App\Modules\Production\Livewire\Concerns\RunsActions;
use App\Modules\Production\Models\WageWork;
use App\Modules\Production\Services\WageWorks;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Wage work')]
class Index extends Component
{
    use RunsActions, WithDataTable, WithExport;

    #[Url(except: 'pending')]
    public string $status = 'pending';

    /** @var array<int, string> */
    public array $paidRejected = [];

    public string $reason = '';

    protected function sortable(): array
    {
        return ['work_date', 'quantity', 'amount'];
    }

    /** @return Builder<WageWork> */
    private function query(): Builder
    {
        $query = WageWork::query()->with(['employee', 'ptc', 'stage'])->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('name', 'like', $like)->orWhere('code', 'like', $like))->orWhereHas('ptc', fn ($p) => $p->where('number', 'like', $like)));
        }

        return $this->applyDateRange($query, 'work_date');
    }

    public function approve(int $id, WageWorks $wages): void
    {
        $this->authorize('wage_work.approve');
        $this->attempt(fn () => $wages->approve(WageWork::query()->findOrFail($id), (string) ($this->paidRejected[$id] ?? '0') ?: '0'), 'Approved and posted to the wages payable.');
    }

    public function reject(int $id, WageWorks $wages): void
    {
        $this->authorize('wage_work.approve');
        $this->attempt(fn () => $wages->reject(WageWork::query()->findOrFail($id), $this->reason), 'Rejected.');
    }

    protected function exportPermission(): string
    {
        return 'wage_work.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('work_date');
    }

    protected function exportColumns(): array
    {
        $cols = ['Date' => fn (WageWork $w) => Format::date($w->work_date), 'Employee' => fn (WageWork $w) => $w->employee->name,
            'PTC' => fn (WageWork $w) => $w->ptc?->number, 'Stage' => fn (WageWork $w) => $w->stage?->name, 'Accepted' => 'quantity', 'Rejected' => 'rejected_qty', 'Status' => 'status'];

        return auth()->user()?->can('salary.view') ? $cols + ['Rate' => 'rate', 'Amount' => 'amount'] : $cols;
    }

    public function render(): View
    {
        return view('production::livewire.wage-work.index', [
            'rows' => $this->applySorting($this->query(), 'work_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
