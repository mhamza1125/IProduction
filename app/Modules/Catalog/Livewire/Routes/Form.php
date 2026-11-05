<?php

namespace App\Modules\Catalog\Livewire\Routes;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Models\RouteStage;
use App\Modules\Core\Models\Stage;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * A route is the ordered list of stages for an item. A PTC copies it when
 * created, so editing a route never changes PTCs already running.
 */
class Form extends Component
{
    #[Locked]
    public ?ProductionRoute $productionRoute = null;

    #[Url(as: 'item')]
    public ?int $item_id = null;

    public string $name = 'Standard';

    public bool $is_default = true;

    public bool $is_active = true;

    public string $notes = '';

    /** @var list<array{stage_id: int|string|null, is_optional: bool, default_work_type: string, wage_rate: string, contractor_rate: string, machine: string, instructions: string}> */
    public array $lines = [];

    public function mount(?ProductionRoute $productionRoute = null): void
    {
        if ($productionRoute?->exists) {
            $this->productionRoute = $productionRoute;
            $this->item_id = $productionRoute->item_id;
            $this->name = $productionRoute->name;
            $this->is_default = $productionRoute->is_default;
            $this->is_active = $productionRoute->is_active;
            $this->notes = (string) $productionRoute->notes;
            $canSeeCost = auth()->user()?->can('cost.view') ?? false;

            foreach ($productionRoute->stages as $s) {
                $this->lines[] = [
                    'stage_id' => $s->stage_id,
                    'is_optional' => $s->is_optional,
                    'default_work_type' => $s->default_work_type,
                    'wage_rate' => $canSeeCost ? (string) $s->wage_rate : '',
                    'contractor_rate' => $canSeeCost ? (string) $s->contractor_rate : '',
                    'machine' => (string) $s->machine,
                    'instructions' => (string) $s->instructions,
                ];
            }
        } else {
            $this->is_default = $this->item_id === null || ! ProductionRoute::query()->where('item_id', $this->item_id)->exists();
            $this->addLine();
        }
    }

    public function addLine(): void
    {
        $this->lines[] = ['stage_id' => null, 'is_optional' => false, 'default_work_type' => 'employee', 'wage_rate' => '', 'contractor_rate' => '', 'machine' => '', 'instructions' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    public function move(int $index, int $direction): void
    {
        $target = $index + $direction;

        if (isset($this->lines[$index], $this->lines[$target])) {
            [$this->lines[$index], $this->lines[$target]] = [$this->lines[$target], $this->lines[$index]];
        }
    }

    public function save(): void
    {
        $this->authorize($this->productionRoute ? 'routes.edit' : 'routes.create');

        // A route always stays with the item it was created for.
        if ($this->productionRoute) {
            $this->item_id = $this->productionRoute->item_id;
        }

        $canSeeCost = auth()->user()?->can('cost.view') ?? false;

        $data = $this->validate([
            'item_id' => ['required', Rule::exists('items', 'id')->where('can_make', true)->where('is_active', true)->whereNull('deleted_at')],
            'name' => ['required', 'string', 'max:100'],
            'is_default' => ['boolean'],
            'is_active' => ['boolean'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.stage_id' => ['required', 'distinct', Rule::exists('stages', 'id')->where('is_active', true)->whereNull('deleted_at')],
            'lines.*.is_optional' => ['boolean'],
            'lines.*.default_work_type' => ['required', Rule::in(array_keys(RouteStage::WORK_TYPES))],
            'lines.*.wage_rate' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.contractor_rate' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.machine' => ['nullable', 'string', 'max:100'],
            'lines.*.instructions' => ['nullable', 'string', 'max:2000'],
        ], ['lines.*.stage_id.distinct' => 'A stage can appear only once in a route.', 'item_id.exists' => 'Choose an active item that can be made.'], ['lines.*.stage_id' => 'stage']);

        $route = DB::transaction(function () use ($data, $canSeeCost) {
            $route = $this->productionRoute ?? new ProductionRoute;
            $oldRates = $route->exists ? $route->stages->keyBy('stage_id') : collect();

            $route->fill([
                'item_id' => $data['item_id'],
                'name' => $data['name'],
                'is_default' => $data['is_default'],
                'is_active' => $data['is_active'],
                'notes' => $data['notes'] ?: null,
            ])->save();

            if ($route->is_default) {
                ProductionRoute::query()->where('item_id', $route->item_id)->whereKeyNot($route->id)->update(['is_default' => false]);
            }

            $route->stages()->delete();

            foreach (array_values($data['lines']) as $i => $line) {
                // Users without cost.view keep the existing rates.
                $old = $oldRates->get($line['stage_id']);
                $route->stages()->create([
                    'stage_id' => $line['stage_id'],
                    'sequence' => $i + 1,
                    'is_optional' => $line['is_optional'],
                    'default_work_type' => $line['default_work_type'],
                    'wage_rate' => $canSeeCost ? ($line['wage_rate'] === '' ? null : $line['wage_rate']) : $old?->wage_rate,
                    'contractor_rate' => $canSeeCost ? ($line['contractor_rate'] === '' ? null : $line['contractor_rate']) : $old?->contractor_rate,
                    'machine' => $line['machine'] ?: null,
                    'instructions' => $line['instructions'] ?: null,
                ]);
            }

            activity('routes')->performedOn($route)->event('saved')
                ->withProperties(['stages' => array_column($data['lines'], 'stage_id')])->log('Saved route stages');

            return $route;
        });

        session()->flash('toast', 'Route saved.');
        $this->redirectRoute('catalog.items.show', $route->item_id, navigate: true);
    }

    public function delete(): void
    {
        $this->authorize('routes.delete');
        abort_if($this->productionRoute === null, 404);

        try {
            $itemId = $this->productionRoute->item_id;
            $this->productionRoute->deleteIfUnused();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        session()->flash('toast', 'Route deleted.');
        $this->redirectRoute('catalog.items.show', $itemId, navigate: true);
    }

    public function render(): View
    {
        return view('catalog::livewire.routes.form', [
            'items' => Item::query()->where('can_make', true)
                ->where(fn ($q) => $q->where('is_active', true)->when($this->item_id, fn ($q) => $q->orWhere('id', $this->item_id)))
                ->orderBy('code')->get()->mapWithKeys(fn (Item $i) => [$i->id => $i->label()]),
            'stages' => Stage::query()->active()->ordered()->pluck('name', 'id'),
            'workTypes' => RouteStage::WORK_TYPES,
            'canSeeCost' => auth()->user()?->can('cost.view') ?? false,
            'canEdit' => auth()->user()?->can($this->productionRoute ? 'routes.edit' : 'routes.create') ?? false,
        ])->title($this->productionRoute ? 'Route: '.$this->productionRoute->name : 'New route');
    }
}
