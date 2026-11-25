<?php

namespace App\Modules\Production\Livewire\WorkerRates;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Stage;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\WorkerRate;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Individual piece rates per worker and stage (optionally per item). They
 * override the route's standard stage rate from their effective date; see
 * Production\Services\StageRates for the order in which rates apply.
 */
#[Title('Worker rates')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $workerType = '';

    #[Url(except: '')]
    public string $stageFilter = '';

    #[Url(except: 'active')]
    public string $status = 'active';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $worker_type = 'employee';

    public ?int $employee_id = null;

    public ?int $contractor_id = null;

    public ?int $stage_id = null;

    public ?int $item_id = null;

    public string $rate = '';

    public string $effective_from = '';

    public string $notes = '';

    public bool $is_active = true;

    protected function sortable(): array
    {
        // Sorting by rate would reveal the order of pay to people who cannot see it.
        return auth()->user()?->can('salary.view') ? ['effective_from', 'rate'] : ['effective_from'];
    }

    public function create(): void
    {
        $this->authorize('worker_rates.create');
        $this->authorize('salary.view'); // setting pay needs seeing pay
        $this->resetValidation();
        $this->reset('editingId', 'employee_id', 'contractor_id', 'stage_id', 'item_id', 'rate', 'notes', 'is_active');
        $this->worker_type = 'employee';
        $this->effective_from = now()->toDateString();
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $this->authorize('worker_rates.edit');
        $this->authorize('salary.view');
        $r = WorkerRate::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $r->id;
        $this->worker_type = $r->worker_type;
        $this->employee_id = $r->employee_id;
        $this->contractor_id = $r->contractor_id;
        $this->stage_id = $r->stage_id;
        $this->item_id = $r->item_id;
        $this->rate = Format::qty($r->rate);
        $this->effective_from = $r->effective_from->toDateString();
        $this->notes = (string) $r->notes;
        $this->is_active = $r->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'worker_rates.edit' : 'worker_rates.create');
        $this->authorize('salary.view');
        $this->rate = str_replace(',', '', $this->rate);

        $employee = $this->worker_type === 'employee';
        $data = $this->validate([
            'worker_type' => ['required', Rule::in(['employee', 'contractor'])],
            'employee_id' => [Rule::requiredIf($employee), 'nullable', Rule::exists('employees', 'id')->where('employee_type', Employee::TYPE_WAGE)],
            'contractor_id' => [Rule::requiredIf(! $employee), 'nullable', Rule::exists('contractors', 'id')],
            'stage_id' => ['required', Rule::exists('stages', 'id')],
            'item_id' => ['nullable', Rule::exists('items', 'id')],
            'rate' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'effective_from' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
            'is_active' => ['boolean'],
        ], ['employee_id.exists' => 'Choose a wage (piece-rate) employee; salaried staff are not paid by piece.']);

        $data['employee_id'] = $employee ? $data['employee_id'] : null;
        $data['contractor_id'] = $employee ? null : $data['contractor_id'];
        $data['notes'] = $data['notes'] ?: null;

        $duplicate = WorkerRate::query()
            ->where('worker_type', $data['worker_type'])->where('employee_id', $data['employee_id'])->where('contractor_id', $data['contractor_id'])
            ->where('stage_id', $data['stage_id'])->where('item_id', $data['item_id'])->whereDate('effective_from', $data['effective_from'])
            ->when($this->editingId, fn ($q) => $q->whereKeyNot($this->editingId))->exists();
        if ($duplicate) {
            $this->addError('effective_from', 'This worker already has a rate for this stage and item from this date. Edit that one instead.');

            return;
        }

        if ($this->editingId) {
            WorkerRate::query()->findOrFail($this->editingId)->update($data);
        } else {
            WorkerRate::query()->create($data + ['created_by' => auth()->id()]);
        }

        $this->showForm = false;
        $this->dispatch('toast', message: 'Rate saved. It applies to work received from '.Format::date($data['effective_from']).' onwards.');
    }

    /**
     * Rates are never deleted: a deactivated rate stays on record for the work
     * it was in force for, and stops applying to new work.
     */
    public function deactivate(int $id): void
    {
        $this->authorize('worker_rates.delete');
        WorkerRate::query()->findOrFail($id)->update(['is_active' => false]);
        $this->dispatch('toast', message: 'Rate deactivated. Work already recorded keeps the rate it was paid at.');
    }

    /** @return Builder<WorkerRate> */
    private function query(): Builder
    {
        $query = WorkerRate::query()->with(['employee', 'contractor', 'stage', 'item'])
            ->when($this->workerType !== '', fn ($q) => $q->where('worker_type', $this->workerType))
            ->when($this->stageFilter !== '', fn ($q) => $q->where('stage_id', (int) $this->stageFilter))
            ->when($this->status === 'active', fn ($q) => $q->where('is_active', true))
            ->when($this->status === 'inactive', fn ($q) => $q->where('is_active', false));

        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->whereHas('employee', fn ($e) => $e->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orWhereHas('contractor', fn ($c) => $c->where('name', 'like', $like)->orWhere('code', 'like', $like))
                ->orWhereHas('stage', fn ($s) => $s->where('name', 'like', $like))
                ->orWhereHas('item', fn ($i) => $i->where('name', 'like', $like)->orWhere('code', 'like', $like)));
        }

        return $this->applyDateRange($query, 'effective_from');
    }

    protected function exportPermission(): string
    {
        return 'worker_rates.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->applySorting($this->query(), 'effective_from', 'desc');
    }

    protected function exportColumns(): array
    {
        return [
            'Worker' => fn (WorkerRate $r) => $r->workerName(),
            'Type' => fn (WorkerRate $r) => ucfirst($r->worker_type),
            'Stage' => fn (WorkerRate $r) => $r->stage->name,
            'Item' => fn (WorkerRate $r) => $r->item ? $r->item->code.' '.$r->item->name : 'All items',
            'Effective from' => fn (WorkerRate $r) => Format::date($r->effective_from),
            'Active' => fn (WorkerRate $r) => $r->is_active ? 'Yes' : 'No',
            'Notes' => 'notes',
        ] + (auth()->user()?->can('salary.view') ? ['Rate' => fn (WorkerRate $r) => (string) $r->rate] : []);
    }

    public function render(): View
    {
        return view('production::livewire.worker-rates.index', [
            'rates' => $this->applySorting($this->query(), 'effective_from', 'desc')->paginate($this->perPage),
            'stages' => Stage::query()->ordered()->pluck('name', 'id'),
            'employees' => Employee::query()->where('employee_type', Employee::TYPE_WAGE)->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'contractors' => Contractor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'items' => Item::query()->where('is_active', true)->orderBy('code')->get(['id', 'code', 'name'])->mapWithKeys(fn ($i) => [$i->id => $i->code.' · '.$i->name]),
        ]);
    }
}
