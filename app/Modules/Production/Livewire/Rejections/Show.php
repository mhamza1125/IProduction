<?php

namespace App\Modules\Production\Livewire\Rejections;

use App\Modules\Production\Livewire\Concerns\RunsActions;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\Rejection;
use App\Modules\Production\Models\RejectionDecision;
use App\Modules\Production\Services\Rejections;
use App\Support\Documents\DocumentWorkflow;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    use RunsActions;

    #[Locked]
    public Rejection $rejection;

    public string $decision = 'scrap';

    public string $quantity = '';

    public ?int $target_ptc_id = null;

    public ?int $target_stage_id = null;

    public string $amount = '';

    public string $reason = '';

    public string $cancel_reason = '';

    public function mount(Rejection $rejection): void
    {
        $this->rejection = $rejection;
    }

    public function decide(Rejections $rejections): void
    {
        $this->authorize('rejections.create');
        $this->validate([
            'decision' => ['required', 'in:'.implode(',', array_keys(RejectionDecision::DECISIONS))],
            'quantity' => ['nullable', 'numeric', 'gt:0'],
            'amount' => ['nullable', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
        ]);
        if ($this->attempt(fn () => $rejections->decide($this->rejection, [
            'decision' => $this->decision, 'quantity' => $this->quantity, 'target_ptc_id' => $this->target_ptc_id, 'target_stage_id' => $this->target_stage_id,
            'amount' => $this->amount, 'reason' => $this->reason,
        ]), 'Decision saved. It needs approval before posting.')) {
            $this->reset('quantity', 'amount', 'reason', 'target_ptc_id', 'target_stage_id');
        }
    }

    public function act(int $id, string $action, DocumentWorkflow $workflow, Rejections $rejections): void
    {
        $d = $this->rejection->decisions()->findOrFail($id);
        $this->authorize('rejections.'.match ($action) {
            'approve' => 'approve', 'post' => 'post', default => 'cancel'
        });
        $this->attempt(fn () => match ($action) {
            'approve' => $workflow->approve($d),
            'post' => $rejections->post($d),
            'cancel' => $workflow->cancel($d, $this->cancel_reason),
            default => abort(404),
        }, ucfirst($action === 'post' ? 'posted' : ($action === 'approve' ? 'approved' : 'cancelled')).'.');
        $this->rejection->refresh();
    }

    public function render(Rejections $rejections): View
    {
        $r = $this->rejection->load(['variant', 'stage', 'lot', 'location', 'ptc', 'job', 'responsible', 'decisions.targetPtc']);
        $targets = Ptc::query()->whereIn('status', Ptc::ACTIVE)->where('item_variant_id', $r->item_variant_id)->get();

        return view('production::livewire.rejections.show', [
            'open' => $rejections->open($r),
            'decisions' => RejectionDecision::DECISIONS,
            'targets' => $targets->pluck('number', 'id'),
            'targetStages' => $this->target_ptc_id ? $targets->firstWhere('id', $this->target_ptc_id)?->stages()->get()->mapWithKeys(fn ($s) => [$s->stage_id => $s->stage->name]) ?? collect() : collect(),
        ])->title($r->number);
    }
}
