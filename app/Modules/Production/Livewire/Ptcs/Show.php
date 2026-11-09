<?php

namespace App\Modules\Production\Livewire\Ptcs;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Livewire\Concerns\RunsActions;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcCost;
use App\Modules\Production\Models\PtcLink;
use App\Modules\Production\Models\PtcStage;
use App\Modules\Production\Services\PtcCosting;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\Worker;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * PTC screen: stage board, transactions, material reconciliation, cost,
 * children and merges, timeline.
 */
class Show extends Component
{
    use RunsActions;

    #[Locked]
    public Ptc $ptc;

    public string $reason = '';

    // Transaction form
    public string $type = 'material_issue';

    public ?int $ptc_stage_id = null;

    public string $worker_type = 'employee';

    public ?int $employee_id = null;

    public ?int $contractor_id = null;

    public ?int $material_variant_id = null;

    public string $quantity = '';

    public string $good = '';

    public string $rejected = '';

    public string $tx_reason = '';

    public string $over_reason = '';

    public bool $from_stock = false;

    public string $date = '';

    /** @var array<string, string> held key => quantity to return / waste */
    public array $held = [];

    // Child / merge
    public string $child_qty = '';

    public ?int $child_stage_id = null;

    public ?int $target_ptc_id = null;

    public ?int $move_stage_id = null;

    public string $move_qty = '';

    public function mount(Ptc $ptc): void
    {
        $this->ptc = $ptc;
        $this->date = now()->format('Y-m-d');
    }

    #[On('document-updated')]
    public function refreshPtc(): void
    {
        $this->ptc->refresh();
    }

    private function worker(): Worker
    {
        return Worker::from($this->worker_type, $this->employee_id, $this->contractor_id);
    }

    private function stage(): PtcStage
    {
        return $this->ptc->stages()->whereKey($this->ptc_stage_id)->firstOrFail();
    }

    public function approve(Ptcs $ptcs): void
    {
        $this->authorize('ptcs.approve');
        $this->attempt(fn () => $ptcs->approve($this->ptc), 'Approved: route and BOM copied, output lot created.');
        $this->ptc->refresh();
    }

    public function delete(): void
    {
        $this->authorize('ptcs.delete');
        abort_unless($this->ptc->isDraft(), 403);
        $this->ptc->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('production.ptcs.index', navigate: true);
    }

    public function status(string $action, Ptcs $ptcs): void
    {
        $permission = match ($action) {
            'cancel' => 'ptcs.cancel',
            'override_complete' => 'ptcs.override',
            default => 'ptcs.edit',
        };
        $this->authorize($permission);
        $this->attempt(fn () => match ($action) {
            'hold' => $ptcs->hold($this->ptc, $this->reason),
            'resume' => $ptcs->resume($this->ptc),
            'cancel' => $ptcs->cancel($this->ptc, $this->reason),
            'complete' => $ptcs->complete($this->ptc),
            'override_complete' => $ptcs->complete($this->ptc, $this->reason ?: 'Completed with open children'),
            'close_early' => $ptcs->closeEarly($this->ptc, $this->reason),
            default => abort(404),
        }, 'Done.');
        $this->reason = '';
        $this->ptc->refresh();
    }

    public function record(PtcTransactions $tx): void
    {
        $this->authorize('production_transactions.create');
        $this->validate([
            'type' => ['required', 'in:material_issue,extra_issue,stage_issue,stage_receive,reject,release,skip'],
            'ptc_stage_id' => [in_array($this->type, ['stage_issue', 'stage_receive', 'reject', 'skip', 'extra_issue'], true) ? 'required' : 'nullable', 'exists:ptc_stages,id'],
            'material_variant_id' => [in_array($this->type, ['material_issue', 'extra_issue'], true) ? 'required' : 'nullable', 'exists:item_variants,id'],
            'quantity' => [in_array($this->type, ['stage_receive', 'skip'], true) ? 'nullable' : 'required', 'nullable', 'numeric', 'gt:0'],
            'good' => ['nullable', 'numeric', 'min:0'],
            'rejected' => ['nullable', 'numeric', 'min:0'],
            'date' => ['required', 'date'],
        ]);
        $date = Carbon::parse($this->date);
        $qty = (string) $this->quantity;

        $done = $this->attempt(fn () => match ($this->type) {
            'material_issue' => $tx->issueMaterial($this->ptc, $this->ptc_stage_id ? $this->stage() : null, (int) $this->material_variant_id, $qty, $this->worker(), $date),
            'extra_issue' => $tx->issueMaterial($this->ptc, $this->stage(), (int) $this->material_variant_id, $qty, $this->worker(), $date, true, $this->tx_reason),
            'stage_issue' => $tx->issueStage($this->ptc, $this->stage(), $qty, $this->worker(), $date, $this->from_stock),
            'stage_receive' => $tx->receiveStage($this->ptc, $this->stage(), $this->worker(), (string) ($this->good ?: '0'), (string) ($this->rejected ?: '0'), $date, $this->tx_reason, $this->over_reason ?: null),
            'reject' => $tx->rejectAtStage($this->ptc, $this->stage(), $qty, $this->tx_reason, $date, auth()->user()?->name),
            'release' => $tx->release($this->ptc, $qty, $date),
            'skip' => $tx->skipStage($this->ptc, $this->stage(), $this->tx_reason, $date),
            default => abort(404),
        }, PtcTransactionLabel::for($this->type).' recorded.');

        if ($done) {
            $this->reset('quantity', 'good', 'rejected', 'tx_reason', 'over_reason', 'from_stock');
            $this->ptc->refresh();
        }
    }

    public function returnHeld(string $key, string $action, PtcTransactions $tx, PtcCosting $costing): void
    {
        $this->authorize('production_transactions.create');
        $row = $costing->heldMaterial($this->ptc)->get($key) ?? abort(404);
        $qty = (string) ($this->held[$key] ?? '');
        if (! is_numeric($qty) || (float) $qty <= 0) {
            $this->addError("held.$key", 'Enter a quantity.');

            return;
        }
        $date = Carbon::parse($this->date);
        $this->attempt(fn () => $action === 'waste'
            ? $tx->wastage($this->ptc, $row['item_variant_id'], $row['lot_id'], $row['location_id'], $qty, $this->tx_reason, $date)
            : $tx->returnToStore($this->ptc, $row['item_variant_id'], $row['lot_id'], $row['location_id'], $qty, $date, $this->tx_reason ?: null),
            $action === 'waste' ? 'Wastage recorded.' : 'Returned to store.');
        unset($this->held[$key]);
    }

    public function createChild(Ptcs $ptcs): void
    {
        $this->authorize('ptcs.create');
        $this->validate(['child_qty' => ['required', 'numeric', 'gt:0'], 'child_stage_id' => ['nullable', 'exists:stages,id']]);
        $child = null;
        if ($this->attempt(function () use ($ptcs, &$child) {
            $child = $ptcs->createChild($this->ptc, (string) $this->child_qty, $this->child_stage_id);
        }, 'Child PTC created as a draft.')) {
            $this->redirectRoute('production.ptcs.show', $child, navigate: true);
        }
    }

    public function move(string $kind, Ptcs $ptcs): void
    {
        $this->authorize('ptcs.edit');
        $this->validate(['target_ptc_id' => ['required', 'exists:ptcs,id'], 'move_stage_id' => ['required', 'exists:stages,id'], 'move_qty' => ['required', 'numeric', 'gt:0']]);
        $target = Ptc::query()->findOrFail($this->target_ptc_id);
        $this->attempt(fn () => $kind === 'split'
            ? $ptcs->splitToChild($this->ptc, $target, (int) $this->move_stage_id, (string) $this->move_qty)
            : $ptcs->merge($this->ptc, $target, (int) $this->move_stage_id, (string) $this->move_qty), $kind === 'split' ? 'Moved to the child PTC.' : 'Merged.');
        $this->reset('move_qty');
        $this->ptc->refresh();
    }

    public function render(PtcCosting $costing, PtcTransactions $tx): View
    {
        $ptc = $this->ptc->load(['variant.item.stockUnit', 'orderLine.order.customer', 'route', 'parent', 'children', 'outputLot', 'responsible', 'startStage', 'endStage']);
        $stages = $ptc->stages()->get();

        $balances = $ptc->output_lot_id ? StockBalance::query()->where('lot_id', $ptc->output_lot_id)->where('quantity', '>', 0)->get() : collect();
        $board = $stages->map(function (PtcStage $s) use ($balances) {
            $at = $balances->where('stage_id', $s->stage_id);

            return [
                'stage' => $s,
                'available' => Decimal::sum($at->where('status', StockStatus::AVAILABLE)->where('location_id', '!=', Location::mainStore()->id)->pluck('quantity'), 4),
                'in_production' => Decimal::sum($at->where('status', StockStatus::IN_PRODUCTION)->pluck('quantity'), 4),
                'rejected' => Decimal::sum($at->where('status', StockStatus::REJECTED)->pluck('quantity'), 4),
                'pending' => Decimal::sub(Decimal::sub($s->issued_qty, $s->received_qty, 4), $s->rejected_qty, 4),
            ];
        });

        $transactions = $ptc->transactions()->with(['ptcStage', 'employee', 'contractor', 'variant', 'lot', 'creator'])->get();
        $heldMaterial = $ptc->isDraft() ? collect() : $costing->heldMaterial($ptc);
        $variantNames = ItemVariant::withTrashed()->with('item')->whereIn('id', $heldMaterial->pluck('item_variant_id')->merge($ptc->materials()->pluck('item_variant_id')))->get()->keyBy('id');

        return view('production::livewire.ptcs.show', [
            'stages' => $stages,
            'board' => $board,
            'holders' => $ptc->isDraft() ? [] : $tx->holders($ptc),
            'transactions' => $transactions,
            'materials' => $ptc->materials()->with('variant.item')->get(),
            'heldMaterial' => $heldMaterial,
            'reconciliation' => $ptc->isDraft() ? collect() : $costing->reconciliation($ptc),
            'variantNames' => $variantNames,
            'costs' => auth()->user()->can('cost.view') ? $costing->byCategory($ptc) : null,
            'pool' => auth()->user()->can('cost.view') ? $costing->pool($ptc) : null,
            'total' => auth()->user()->can('cost.view') ? $costing->total($ptc) : null,
            'materialOptions' => ItemVariant::query()->active()->where('id', '!=', $ptc->item_variant_id)->with('item')->get()->sortBy('sku')->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()]),
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'contractors' => Contractor::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'links' => PtcLink::query()->with(['ptc', 'source', 'stage'])->where(fn ($q) => $q->where('ptc_id', $ptc->id)->orWhere('source_ptc_id', $ptc->id))->get(),
            'mergeTargets' => Ptc::query()->whereIn('status', Ptc::ACTIVE)->where('id', '!=', $ptc->id)->where('item_variant_id', $ptc->item_variant_id)->where('route_id', $ptc->route_id)
                ->where(fn ($q) => $ptc->sales_order_line_id ? $q->where('sales_order_line_id', $ptc->sales_order_line_id) : $q->whereNull('sales_order_line_id'))->pluck('number', 'id'),
            'costCategories' => PtcCost::CATEGORIES,
        ])->title($ptc->number);
    }
}
