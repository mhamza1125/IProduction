<?php

namespace App\Modules\Production\Livewire\Jobs;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\People\Models\Contractor;
use App\Modules\Production\Models\ContractorJob;
use App\Modules\Production\Services\ContractorJobs;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?ContractorJob $job = null;

    public ?int $contractor_id = null;

    public ?int $sales_order_line_id = null;

    public string $job_date = '';

    public ?string $due_date = null;

    public string $notes = '';

    /** @var list<array{item_variant_id: int|string|null, stage_id: int|string|null, quantity: string, rate: string}> */
    public array $lines = [];

    /** @var list<array{item_variant_id: int|string|null, expected_qty: string}> */
    public array $materials = [];

    public function mount(?ContractorJob $job = null): void
    {
        if ($job?->exists) {
            abort_unless($job->isDraft(), 403, 'Only drafts can be edited.');
            $this->job = $job;
            $this->contractor_id = $job->contractor_id;
            $this->sales_order_line_id = $job->sales_order_line_id;
            $this->job_date = $job->job_date->format('Y-m-d');
            $this->due_date = $job->due_date?->format('Y-m-d');
            $this->notes = (string) $job->notes;
            $this->lines = $job->lines->map(fn ($l) => ['item_variant_id' => $l->item_variant_id, 'stage_id' => $l->stage_id, 'quantity' => Decimal::round($l->quantity, 4), 'rate' => Decimal::round($l->rate, 4)])->all();
            $this->materials = $job->materials->map(fn ($m) => ['item_variant_id' => $m->item_variant_id, 'expected_qty' => Decimal::round($m->expected_qty, 4)])->all();

            return;
        }
        $this->job_date = now()->format('Y-m-d');
        $this->addLine();
    }

    public function addLine(): void
    {
        $this->lines[] = ['item_variant_id' => null, 'stage_id' => null, 'quantity' => '', 'rate' => ''];
    }

    public function removeLine(int $i): void
    {
        unset($this->lines[$i]);
        $this->lines = array_values($this->lines);
    }

    public function addMaterial(): void
    {
        $this->materials[] = ['item_variant_id' => null, 'expected_qty' => ''];
    }

    public function removeMaterial(int $i): void
    {
        unset($this->materials[$i]);
        $this->materials = array_values($this->materials);
    }

    public function save(ContractorJobs $jobs): void
    {
        $this->authorize($this->job ? 'contractor_jobs.edit' : 'contractor_jobs.create');
        $this->validate([
            'contractor_id' => ['required', 'exists:contractors,id'],
            'sales_order_line_id' => ['nullable', 'exists:sales_order_lines,id'],
            'job_date' => ['required', 'date'],
            'due_date' => ['nullable', 'date', 'after_or_equal:job_date'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_variant_id' => ['required', 'exists:item_variants,id'],
            'lines.*.stage_id' => ['nullable', 'exists:stages,id'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.rate' => ['required', 'numeric', 'min:0'],
            'materials.*.item_variant_id' => ['required', 'exists:item_variants,id'],
            'materials.*.expected_qty' => ['required', 'numeric', 'gt:0'],
        ]);
        try {
            $job = $jobs->save($this->job, ['contractor_id' => $this->contractor_id, 'sales_order_line_id' => $this->sales_order_line_id, 'job_date' => $this->job_date, 'due_date' => $this->due_date, 'notes' => $this->notes], $this->lines, $this->materials);
        } catch (DocumentException $e) {
            $this->addError('contractor_id', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$job->number} saved.");
        $this->redirectRoute('production.jobs.show', $job, navigate: true);
    }

    public function render(): View
    {
        $variants = ItemVariant::query()->active()->with('item')->get()->sortBy('sku')->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()]);

        return view('production::livewire.jobs.form', [
            'contractors' => Contractor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'variants' => $variants,
            'stages' => Stage::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'),
            'orderLines' => SalesOrderLine::query()->with(['order.customer', 'variant'])->whereHas('order', fn ($o) => $o->whereIn('status', SalesOrder::OPEN))->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->order->number.' · '.$l->variant->sku.' · '.$l->order->customer->name]),
        ])->title($this->job ? 'Edit '.$this->job->number : 'New contractor job');
    }
}
