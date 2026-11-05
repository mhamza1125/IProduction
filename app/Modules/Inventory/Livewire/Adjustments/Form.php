<?php

namespace App\Modules\Inventory\Livewire\Adjustments;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Inventory\Models\StockStatus;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Draft stock adjustment. Positive quantities add stock, negative remove it.
 */
class Form extends Component
{
    #[Locked]
    public ?StockAdjustment $adjustment = null;

    public ?string $adjustment_date = null;

    public ?int $reason_id = null;

    public string $notes = '';

    /** @var list<array{item_variant_id: int|string|null, stage_id: int|string|null, status: string, location_id: int|string|null, lot_id: int|string|null, quantity: string, unit_cost: string, notes: string}> */
    public array $lines = [];

    public function mount(?StockAdjustment $adjustment = null): void
    {
        if ($adjustment?->exists) {
            abort_unless($adjustment->isDraft(), 403, 'Only drafts can be edited.');
            $this->adjustment = $adjustment;
            $this->adjustment_date = $adjustment->adjustment_date->format('Y-m-d');
            $this->reason_id = $adjustment->reason_id;
            $this->notes = (string) $adjustment->notes;
            $canSeeCost = auth()->user()?->can('cost.view') ?? false;
            $this->lines = $adjustment->lines()->get()->map(fn ($l) => [
                'item_variant_id' => $l->item_variant_id, 'stage_id' => $l->stage_id, 'status' => $l->status,
                'location_id' => $l->location_id, 'lot_id' => $l->lot_id, 'quantity' => rtrim(rtrim($l->quantity, '0'), '.'),
                'unit_cost' => $canSeeCost ? (string) $l->unit_cost : '', 'notes' => (string) $l->notes,
            ])->all();
        } else {
            $this->adjustment_date = now()->format('Y-m-d');
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = [
            'item_variant_id' => null, 'stage_id' => null, 'status' => StockStatus::AVAILABLE,
            'location_id' => Location::mainStore()->id, 'lot_id' => null, 'quantity' => '', 'unit_cost' => '', 'notes' => '',
        ];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function save(): void
    {
        $this->authorize($this->adjustment ? 'stock_adjustments.edit' : 'stock_adjustments.create');
        $canSeeCost = auth()->user()?->can('cost.view') ?? false;

        $data = $this->validate([
            'adjustment_date' => ['required', 'date_format:Y-m-d'],
            'reason_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'adjustment_reason')->where('is_active', true)],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.item_variant_id' => ['required', Rule::exists('item_variants', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.stage_id' => ['nullable', Rule::exists('stages', 'id')],
            'lines.*.status' => ['required', Rule::in(array_keys(StockStatus::LABELS))],
            'lines.*.location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'lines.*.lot_id' => ['nullable', Rule::exists('lots', 'id')],
            'lines.*.quantity' => ['required', 'numeric', 'not_in:0', 'decimal:0,4'],
            'lines.*.unit_cost' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.notes' => ['nullable', 'string', 'max:500'],
        ], ['lines.*.quantity.not_in' => 'Use a positive quantity to add stock or a negative one to remove it.'], ['reason_id' => 'reason', 'lines.*.item_variant_id' => 'item']);

        // An increase into a new lot needs a cost; default it from the item when the user cannot see costs.
        foreach ($data['lines'] as $i => $line) {
            if ((float) $line['quantity'] > 0 && empty($line['lot_id'])) {
                $cost = $canSeeCost ? ($line['unit_cost'] ?? '') : '';
                $data['lines'][$i]['unit_cost'] = $cost !== '' ? $cost : ItemVariant::query()->with('item')->find($line['item_variant_id'])?->purchaseCost();
            } else {
                $data['lines'][$i]['unit_cost'] = null;
            }
            if (! empty($line['lot_id']) && Lot::query()->whereKey($line['lot_id'])->value('item_variant_id') !== (int) $line['item_variant_id']) {
                $this->addError("lines.$i.lot_id", 'This lot belongs to another item.');

                return;
            }
        }

        $adjustment = DB::transaction(function () use ($data) {
            $adjustment = $this->adjustment ?? new StockAdjustment([
                'number' => app(DocumentNumber::class)->next('stock_adjustment', Carbon::parse($data['adjustment_date'])),
                'created_by' => auth()->id(),
            ]);
            $adjustment->fill(['adjustment_date' => $data['adjustment_date'], 'reason_id' => $data['reason_id'], 'notes' => $data['notes'] ?: null])->save();

            $adjustment->lines()->delete();
            foreach ($data['lines'] as $line) {
                $adjustment->lines()->create([
                    'item_variant_id' => $line['item_variant_id'],
                    'stage_id' => $line['stage_id'] ?: null,
                    'status' => $line['status'],
                    'location_id' => $line['location_id'],
                    'lot_id' => $line['lot_id'] ?: null,
                    'quantity' => $line['quantity'],
                    'unit_cost' => $line['unit_cost'],
                    'notes' => $line['notes'] ?: null,
                ]);
            }

            return $adjustment;
        });

        session()->flash('toast', "{$adjustment->number} saved as draft.");
        $this->redirectRoute('inventory.adjustments.show', $adjustment, navigate: true);
    }

    public function render(): View
    {
        $variantIds = collect($this->lines)->pluck('item_variant_id')->filter()->map(fn ($id) => (int) $id)->all();

        return view('inventory::livewire.adjustments.form', [
            'variants' => ItemVariant::query()->active()->with('item')->get()->sortBy(fn ($v) => $v->sku)
                ->mapWithKeys(fn (ItemVariant $v) => [$v->id => $v->fullLabel()]),
            'reasons' => Lookup::options('adjustment_reason', $this->reason_id),
            'stages' => Stage::query()->ordered()->pluck('name', 'id'),
            'statuses' => StockStatus::LABELS,
            'locations' => Location::query()->active()->orderBy('name')->pluck('name', 'id'),
            'lotsByVariant' => StockBalance::query()->whereIn('item_variant_id', $variantIds)->where('quantity', '>', 0)->with('lot')->get()
                ->groupBy('item_variant_id')->map(fn ($rows) => $rows->mapWithKeys(fn ($b) => [$b->lot_id => $b->lot->lot_number])),
            'canSeeCost' => auth()->user()?->can('cost.view') ?? false,
        ])->title($this->adjustment ? 'Edit '.$this->adjustment->number : 'New stock adjustment');
    }
}
