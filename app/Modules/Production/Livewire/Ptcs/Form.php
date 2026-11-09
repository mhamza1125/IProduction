<?php

namespace App\Modules\Production\Livewire\Ptcs;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Models\RouteStage;
use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?Ptc $ptc = null;

    #[Url(as: 'line', except: null)]
    #[Locked]
    public ?int $fromLine = null;

    public ?int $item_variant_id = null;

    public string $planned_qty = '';

    public ?int $route_id = null;

    public ?int $start_stage_id = null;

    public ?int $end_stage_id = null;

    public ?int $sales_order_line_id = null;

    public ?int $responsible_employee_id = null;

    public string $start_date = '';

    public ?string $target_date = null;

    public string $notes = '';

    public function mount(?Ptc $ptc = null): void
    {
        if ($ptc?->exists) {
            abort_unless($ptc->isDraft(), 403, 'Only drafts can be edited.');
            $this->ptc = $ptc;
            $this->fill($ptc->only(['item_variant_id', 'route_id', 'start_stage_id', 'end_stage_id', 'sales_order_line_id', 'responsible_employee_id']));
            $this->planned_qty = Decimal::round($ptc->planned_qty, 4);
            $this->start_date = $ptc->start_date->format('Y-m-d');
            $this->target_date = $ptc->target_date?->format('Y-m-d');
            $this->notes = (string) $ptc->notes;

            return;
        }
        $this->start_date = now()->format('Y-m-d');
        if ($this->fromLine && ($line = SalesOrderLine::query()->with('variant.item')->find($this->fromLine))) {
            $this->sales_order_line_id = $line->id;
            $this->item_variant_id = $line->item_variant_id;
            $this->planned_qty = Decimal::round(Decimal::compare($line->plan_make, '0') > 0 ? $line->plan_make : $line->remaining(), 4);
            $this->route_id = $line->route_id;
            $this->target_date = ($line->due_date ?? $line->order->due_date)?->format('Y-m-d');
            $this->updatedItemVariantId();
        }
    }

    public function updatedItemVariantId(): void
    {
        $itemId = ItemVariant::query()->whereKey($this->item_variant_id)->value('item_id');
        $this->route_id ??= ProductionRoute::query()->where('item_id', $itemId)->where('is_default', true)->where('is_active', true)->value('id');
    }

    public function save(Ptcs $ptcs): void
    {
        $this->authorize($this->ptc ? 'ptcs.edit' : 'ptcs.create');
        $data = $this->validate([
            'item_variant_id' => ['required', 'exists:item_variants,id'],
            'planned_qty' => ['required', 'numeric', 'gt:0'],
            'route_id' => ['required', 'exists:production_routes,id'],
            'start_stage_id' => ['nullable', 'exists:stages,id'],
            'end_stage_id' => ['nullable', 'exists:stages,id'],
            'sales_order_line_id' => ['nullable', 'exists:sales_order_lines,id'],
            'responsible_employee_id' => ['nullable', 'exists:employees,id'],
            'start_date' => ['required', 'date'],
            'target_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $ptc = $ptcs->save($this->ptc, $data);
        } catch (DocumentException $e) {
            $this->addError('item_variant_id', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$ptc->number} saved.");
        $this->redirectRoute('production.ptcs.show', $ptc, navigate: true);
    }

    public function render(): View
    {
        $itemId = ItemVariant::query()->whereKey($this->item_variant_id)->value('item_id');

        return view('production::livewire.ptcs.form', [
            'variants' => ItemVariant::query()->active()->whereHas('item', fn ($i) => $i->where('can_make', true))->with('item')->get()->sortBy('sku')->mapWithKeys(fn ($v) => [$v->id => $v->fullLabel()]),
            'routes' => ProductionRoute::query()->where('item_id', $itemId)->where('is_active', true)->pluck('name', 'id'),
            'stages' => RouteStage::query()->with('stage')->where('route_id', $this->route_id)->orderBy('sequence')->get()->mapWithKeys(fn ($s) => [$s->stage_id => $s->sequence.'. '.$s->stage->name]),
            'lines' => SalesOrderLine::query()->with('order.customer')->where('item_variant_id', $this->item_variant_id)
                ->whereHas('order', fn ($o) => $o->whereIn('status', SalesOrder::OPEN))->get()
                ->mapWithKeys(fn ($l) => [$l->id => $l->order->number.' · '.$l->order->customer->name.' · '.Decimal::round($l->remaining(), 4)]),
            'employees' => Employee::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
        ])->title($this->ptc ? 'Edit '.$this->ptc->number : 'New PTC');
    }
}
