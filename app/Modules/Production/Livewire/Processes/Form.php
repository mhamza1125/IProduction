<?php

namespace App\Modules\Production\Livewire\Processes;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Inventory\Models\Location;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Actions\PostMaterialProcess;
use App\Modules\Production\Models\MaterialProcess;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One-step screen: save the draft, or save, approve and post together.
 */
class Form extends Component
{
    #[Locked]
    public ?MaterialProcess $process = null;

    public string $process_date = '';

    public string $process_name = '';

    public ?string $worker_type = null;

    public ?int $employee_id = null;

    public ?int $contractor_id = null;

    public string $labor_cost = '';

    public string $recoverable_value = '';

    public string $notes = '';

    /** @var list<array<string, mixed>> */
    public array $inputs = [];

    /** @var list<array<string, mixed>> */
    public array $outputs = [];

    /** @var list<array<string, mixed>> */
    public array $losses = [];

    public function mount(?MaterialProcess $process = null): void
    {
        if ($process?->exists) {
            abort_unless($process->isDraft(), 403, 'Only drafts can be edited.');
            $this->process = $process;
            $this->fill($process->only(['process_name', 'worker_type', 'employee_id', 'contractor_id']));
            $this->process_date = $process->process_date->format('Y-m-d');
            $this->labor_cost = (string) $process->labor_cost;
            $this->recoverable_value = (string) $process->recoverable_value;
            $this->notes = (string) $process->notes;
            foreach ($process->lines as $l) {
                match ($l->kind) {
                    'input' => $this->inputs[] = ['item_variant_id' => $l->item_variant_id, 'lot_id' => $l->lot_id, 'quantity' => Decimal::round($l->quantity, 4)],
                    'output' => $this->outputs[] = ['item_variant_id' => $l->item_variant_id, 'expected_qty' => $l->expected_qty === null ? '' : Decimal::round($l->expected_qty, 4), 'quantity' => Decimal::round($l->quantity, 4), 'location_id' => $l->location_id],
                    default => $this->losses[] = ['quantity' => Decimal::round($l->quantity, 4), 'loss_type' => $l->loss_type, 'reason' => $l->reason],
                };
            }

            return;
        }
        $this->process_date = now()->format('Y-m-d');
        $this->inputs[] = ['item_variant_id' => null, 'lot_id' => null, 'quantity' => ''];
        $this->outputs[] = ['item_variant_id' => null, 'expected_qty' => '', 'quantity' => '', 'location_id' => null];
    }

    public function add(string $kind): void
    {
        match ($kind) {
            'input' => $this->inputs[] = ['item_variant_id' => null, 'lot_id' => null, 'quantity' => ''],
            'output' => $this->outputs[] = ['item_variant_id' => null, 'expected_qty' => '', 'quantity' => '', 'location_id' => null],
            default => $this->losses[] = ['quantity' => '', 'loss_type' => 'wastage', 'reason' => ''],
        };
    }

    public function remove(string $kind, int $i): void
    {
        $list = $kind === 'input' ? 'inputs' : ($kind === 'output' ? 'outputs' : 'losses');
        unset($this->{$list}[$i]);
        $this->{$list} = array_values($this->{$list});
    }

    public function save(PostMaterialProcess $post, DocumentWorkflow $workflow, bool $postNow = false): void
    {
        $this->authorize($this->process ? 'material_processes.edit' : 'material_processes.create');
        if ($postNow) {
            $this->authorize('material_processes.post');
        }
        $this->validate([
            'process_date' => ['required', 'date'],
            'process_name' => ['required', 'string', 'max:150'],
            'worker_type' => ['nullable', 'in:employee,contractor'],
            'employee_id' => ['nullable', 'exists:employees,id'],
            'contractor_id' => ['nullable', 'exists:contractors,id'],
            'labor_cost' => ['nullable', 'numeric', 'min:0'],
            'recoverable_value' => ['nullable', 'numeric', 'min:0'],
            'inputs' => ['required', 'array', 'min:1'],
            'inputs.*.item_variant_id' => ['required', 'exists:item_variants,id'],
            'inputs.*.lot_id' => ['nullable', 'exists:lots,id'],
            'inputs.*.quantity' => ['required', 'numeric', 'gt:0'],
            'outputs' => ['required', 'array', 'min:1'],
            'outputs.*.item_variant_id' => ['required', 'exists:item_variants,id'],
            'outputs.*.expected_qty' => ['nullable', 'numeric', 'min:0'],
            'outputs.*.quantity' => ['required', 'numeric', 'gt:0'],
            'outputs.*.location_id' => ['nullable', 'exists:locations,id'],
            'losses.*.quantity' => ['nullable', 'numeric', 'min:0'],
            'losses.*.reason' => ['nullable', 'string', 'max:255'],
        ]);

        $header = ['process_date' => $this->process_date, 'process_name' => $this->process_name, 'worker_type' => $this->worker_type,
            'employee_id' => $this->employee_id, 'contractor_id' => $this->contractor_id, 'labor_cost' => $this->labor_cost,
            'recoverable_value' => $this->recoverable_value, 'notes' => $this->notes];

        try {
            $process = DB::transaction(function () use ($post, $workflow, $header, $postNow) {
                $process = $post->save($this->process, $header, $this->inputs, $this->outputs, $this->losses);
                if ($postNow) {
                    $process = $post($workflow->approve($process));
                }

                return $process;
            });
        } catch (DocumentException $e) {
            $this->addError('process_name', $e->getMessage());

            return;
        }
        session()->flash('toast', $postNow ? "{$process->number} posted." : "{$process->number} saved.");
        $this->redirectRoute('production.processes.show', $process, navigate: true);
    }

    public function saveAndPost(PostMaterialProcess $post, DocumentWorkflow $workflow): void
    {
        $this->authorize('material_processes.approve');
        $this->save($post, $workflow, true);
    }

    public function render(): View
    {
        return view('production::livewire.processes.form', [
            'variants' => ItemVariant::query()->active()->with('item')->get()->sortBy('sku')->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()]),
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'contractors' => Contractor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'locations' => Location::query()->active()->where('type', 'store')->pluck('name', 'id'),
        ])->title($this->process ? 'Edit '.$this->process->number : 'New material processing');
    }
}
