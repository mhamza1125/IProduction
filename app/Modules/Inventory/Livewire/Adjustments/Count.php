<?php

namespace App\Modules\Inventory\Livewire\Adjustments;

use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Models\Lookup;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Services\CountSheet;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Physical count sheet: enter counted quantities; differences become a draft adjustment.
 */
#[Title('Physical count')]
class Count extends Component
{
    #[Url]
    public ?int $location_id = null;

    #[Url(except: '')]
    public string $type = '';

    /** @var array<int, string> balance id => counted */
    public array $counted = [];

    public ?string $count_date = null;

    public ?int $reason_id = null;

    public function mount(): void
    {
        $this->location_id ??= Location::mainStore()->id;
        $this->count_date = now()->format('Y-m-d');
        $this->reason_id = Lookup::query()->ofType('adjustment_reason')->where('name', 'Physical count')->value('id');
    }

    public function updatedLocationId(): void
    {
        $this->counted = [];
    }

    public function createAdjustment(CountSheet $sheet): void
    {
        $this->authorize('stock_adjustments.create');

        $this->validate([
            'location_id' => ['required', Rule::exists('locations', 'id')],
            'count_date' => ['required', 'date_format:Y-m-d'],
            'reason_id' => ['required', Rule::exists('lookups', 'id')->where('type', 'adjustment_reason')],
            'counted.*' => ['nullable', 'numeric', 'min:0'],
        ], [], ['reason_id' => 'reason', 'counted.*' => 'counted quantity']);

        $adjustment = $sheet->toAdjustment((int) $this->location_id, $this->counted, Carbon::parse($this->count_date), $this->reason_id, 'Physical count');

        if ($adjustment === null) {
            $this->dispatch('toast', message: 'No differences: nothing to adjust.');

            return;
        }

        session()->flash('toast', "Draft {$adjustment->number} created from the count.");
        $this->redirectRoute('inventory.adjustments.show', $adjustment, navigate: true);
    }

    public function render(CountSheet $sheet): View
    {
        return view('inventory::livewire.adjustments.count', [
            'rows' => $this->location_id ? $sheet->rows((int) $this->location_id, $this->type ?: null) : collect(),
            'locations' => Location::query()->active()->orderBy('name')->pluck('name', 'id'),
            'types' => Item::TYPES,
            'reasons' => Lookup::options('adjustment_reason', $this->reason_id),
        ]);
    }
}
