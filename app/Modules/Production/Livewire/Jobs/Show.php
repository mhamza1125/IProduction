<?php

namespace App\Modules\Production\Livewire\Jobs;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Production\Livewire\Concerns\RunsActions;
use App\Modules\Production\Models\ContractorJob;
use App\Modules\Production\Services\ContractorJobs;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use RunsActions;

    #[Locked]
    public ContractorJob $job;

    public string $reason = '';

    public string $date = '';

    public ?int $issue_variant_id = null;

    public string $issue_qty = '';

    public ?int $line_id = null;

    public string $received = '';

    public string $accepted = '';

    public string $rejected = '';

    public ?string $over_action = null;

    public string $reject_reason = '';

    /** @var array<string, string> */
    public array $held = [];

    public function mount(ContractorJob $job): void
    {
        $this->job = $job;
        $this->date = now()->format('Y-m-d');
    }

    public function approve(ContractorJobs $jobs): void
    {
        $this->authorize('contractor_jobs.approve');
        $this->attempt(fn () => $jobs->approve($this->job), 'Approved.');
        $this->job->refresh();
    }

    public function returnToDraft(DocumentWorkflow $workflow): void
    {
        $this->authorize('contractor_jobs.approve');
        $this->attempt(fn () => $workflow->returnToDraft($this->job, $this->reason), 'Returned to draft.');
        $this->job->refresh();
    }

    public function cancel(DocumentWorkflow $workflow): void
    {
        $this->authorize('contractor_jobs.cancel');
        $this->attempt(fn () => $workflow->cancel($this->job, $this->reason), 'Cancelled.');
        $this->job->refresh();
    }

    public function delete(): void
    {
        $this->authorize('contractor_jobs.delete');
        abort_unless($this->job->isDraft(), 403);
        $this->job->lines()->delete();
        $this->job->materials()->delete();
        $this->job->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('production.jobs.index', navigate: true);
    }

    public function issue(ContractorJobs $jobs): void
    {
        $this->authorize('contractor_jobs.post');
        $this->validate(['issue_variant_id' => ['required', 'exists:item_variants,id'], 'issue_qty' => ['required', 'numeric', 'gt:0'], 'date' => ['required', 'date']]);
        if ($this->attempt(fn () => $jobs->issueMaterial($this->job, (int) $this->issue_variant_id, (string) $this->issue_qty, Carbon::parse($this->date)), 'Material issued to the contractor.')) {
            $this->reset('issue_qty');
        }
        $this->job->refresh();
    }

    public function receive(ContractorJobs $jobs): void
    {
        $this->authorize('contractor_jobs.post');
        $this->validate(['line_id' => ['required', 'exists:contractor_job_lines,id'], 'received' => ['required', 'numeric', 'gt:0'], 'accepted' => ['required', 'numeric', 'min:0'], 'rejected' => ['nullable', 'numeric', 'min:0'], 'date' => ['required', 'date']]);
        $line = $this->job->lines()->findOrFail($this->line_id);
        if ($this->attempt(fn () => $jobs->receive($this->job, $line, (string) $this->received, (string) $this->accepted, (string) ($this->rejected ?: '0'), Carbon::parse($this->date), $this->over_action, $this->reject_reason ?: null), 'Output received.')) {
            $this->reset('received', 'accepted', 'rejected', 'over_action', 'reject_reason');
        }
        $this->job->refresh();
    }

    public function settleHeld(string $key, string $action, ContractorJobs $jobs): void
    {
        $this->authorize('contractor_jobs.post');
        [$variantId, $lotId] = array_map('intval', explode('-', $key));
        $qty = (string) ($this->held[$key] ?? '');
        if (! is_numeric($qty) || (float) $qty <= 0) {
            $this->addError("held.$key", 'Enter a quantity.');

            return;
        }
        $date = Carbon::parse($this->date);
        $this->attempt(fn () => $action === 'waste'
            ? $jobs->wastage($this->job, $variantId, $lotId, $qty, $this->reason, $date)
            : $jobs->returnMaterial($this->job, $variantId, $lotId, $qty, $date, $this->reason ?: null), $action === 'waste' ? 'Wastage recorded.' : 'Returned to store.');
        unset($this->held[$key]);
    }

    public function close(ContractorJobs $jobs): void
    {
        $this->authorize('contractor_jobs.cancel');
        $this->attempt(fn () => $jobs->close($this->job, $this->reason), 'Job closed.');
        $this->job->refresh();
    }

    public function render(ContractorJobs $jobs): View
    {
        $job = $this->job->load(['contractor', 'lines.variant.item.stockUnit', 'lines.stage', 'materials.variant.item', 'orderLine.order']);
        $held = $job->isDraft() ? collect() : $jobs->held($job);

        return view('production::livewire.jobs.show', [
            'reconciliation' => $job->isDraft() ? collect() : $jobs->reconciliation($job),
            'heldRows' => $held,
            'heldNames' => ItemVariant::withTrashed()->with('item')->whereIn('id', $held->pluck('item_variant_id'))->get()->keyBy('id'),
            'transactions' => $job->transactions()->with(['variant.item', 'lot'])->get(),
            'materialOptions' => $job->materials->mapWithKeys(fn ($m) => [$m->item_variant_id => $m->variant->fullLabel()])
                ->union(ItemVariant::query()->active()->with('item')->get()->sortBy('sku')->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()])),
            'overActions' => ContractorJobs::OVER_RECEIPT_ACTIONS,
        ])->title($job->number);
    }
}
