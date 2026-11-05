<?php

namespace App\Modules\Catalog\Livewire\Boms;

use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Bom;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Services\BomExplosion;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * One BOM version: header, component lines, variant overrides, approval and
 * an explosion preview. Only drafts can be edited.
 */
class Edit extends Component
{
    #[Locked]
    public Bom $bom;

    public ?string $effective_date = null;

    public string $output_quantity = '1';

    public string $notes = '';

    /** @var list<array{id: int|null, component_item_id: int|string|null, component_variant_id: int|string|null, quantity: string, unit_id: int|string|null, wastage_percent: string, stage_id: int|string|null, is_optional: bool, notes: string}> */
    public array $lines = [];

    // Override form
    public bool $showOverride = false;

    public string $match = '';            // "value:12" or "variant:34"

    public ?int $override_line_id = null;  // NULL = add a component

    public ?int $override_item_id = null;

    public ?int $override_variant_id = null;

    public string $override_quantity = '';

    public string $override_wastage = '';

    public bool $override_removes = false;

    // Explosion preview
    public ?int $preview_variant_id = null;

    public string $preview_quantity = '100';

    /** @var list<array<string, mixed>> */
    public array $preview = [];

    public string $previewError = '';

    public function mount(Bom $bom): void
    {
        $this->bom = $bom;
        $this->effective_date = $bom->effective_date->format('Y-m-d');
        $this->output_quantity = rtrim(rtrim($bom->output_quantity, '0'), '.');
        $this->notes = (string) $bom->notes;
        $this->loadLines();
        $this->preview_variant_id = $bom->item->variants()->where('is_active', true)->value('id');
    }

    private function loadLines(): void
    {
        $this->lines = $this->bom->lines()->get()->map(fn ($l) => [
            'id' => $l->id,
            'component_item_id' => $l->component_item_id,
            'component_variant_id' => $l->component_variant_id,
            'quantity' => rtrim(rtrim($l->quantity, '0'), '.'),
            'unit_id' => $l->unit_id,
            'wastage_percent' => rtrim(rtrim($l->wastage_percent, '0'), '.') ?: '0',
            'stage_id' => $l->stage_id,
            'is_optional' => $l->is_optional,
            'notes' => (string) $l->notes,
        ])->all();
    }

    public function addLine(): void
    {
        $this->lines[] = ['id' => null, 'component_item_id' => null, 'component_variant_id' => null, 'quantity' => '', 'unit_id' => null, 'wastage_percent' => '0', 'stage_id' => null, 'is_optional' => false, 'notes' => ''];
    }

    public function removeLine(int $index): void
    {
        unset($this->lines[$index]);
        $this->lines = array_values($this->lines);
    }

    /** Default the unit to the component's stock unit when the component changes. */
    public function updatedLines(mixed $value, string $key): void
    {
        if (str_ends_with($key, '.component_item_id')) {
            $index = (int) explode('.', $key)[0];
            $item = Item::query()->find((int) $value);
            $this->lines[$index]['unit_id'] = $item?->stock_unit_id;
            $this->lines[$index]['component_variant_id'] = $item && $item->variants()->count() === 1 ? $item->variants()->value('id') : null;
        }
    }

    public function save(BomService $boms): void
    {
        $this->authorize('boms.edit');
        $this->ensureDraft();

        $data = $this->validate([
            'effective_date' => ['required', 'date_format:Y-m-d'],
            'output_quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines' => ['array'],
            'lines.*.component_item_id' => ['required', Rule::exists('items', 'id')->where('is_active', true)->whereNull('deleted_at'), Rule::notIn([$this->bom->item_id])],
            'lines.*.component_variant_id' => ['nullable', Rule::exists('item_variants', 'id')->whereNull('deleted_at')],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'lines.*.unit_id' => ['required', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'lines.*.wastage_percent' => ['required', 'numeric', 'min:0', 'max:1000', 'decimal:0,2'],
            'lines.*.stage_id' => ['nullable', Rule::exists('stages', 'id')],
            'lines.*.is_optional' => ['boolean'],
            'lines.*.notes' => ['nullable', 'string', 'max:255'],
        ], ['lines.*.component_item_id.not_in' => 'An item cannot be a component of itself.'], ['lines.*.component_item_id' => 'component', 'lines.*.quantity' => 'quantity']);

        foreach ($data['lines'] as $i => $line) {
            if ($line['component_variant_id'] && ItemVariant::query()->whereKey($line['component_variant_id'])->value('item_id') !== (int) $line['component_item_id']) {
                $this->addError("lines.$i.component_variant_id", 'This variant belongs to another item.');

                return;
            }
        }

        try {
            $boms->assertNotCircular($this->bom->item_id, array_map(fn ($l) => (int) $l['component_item_id'], $data['lines']));
        } catch (DocumentException $e) {
            $this->addError('lines', $e->getMessage());

            return;
        }

        DB::transaction(function () use ($data) {
            $this->bom->update([
                'effective_date' => $data['effective_date'],
                'output_quantity' => $data['output_quantity'],
                'notes' => $data['notes'] ?: null,
            ]);

            $keep = [];
            foreach (array_values($data['lines']) as $i => $line) {
                $attributes = [
                    'component_item_id' => $line['component_item_id'],
                    'component_variant_id' => $line['component_variant_id'] ?: null,
                    'quantity' => $line['quantity'],
                    'unit_id' => $line['unit_id'],
                    'wastage_percent' => $line['wastage_percent'],
                    'stage_id' => $line['stage_id'] ?: null,
                    'is_optional' => $line['is_optional'],
                    'notes' => $line['notes'] ?: null,
                    'sort_order' => $i,
                ];
                $id = $this->lines[$i]['id'] ?? null;
                $model = $id ? $this->bom->lines()->findOrFail($id) : $this->bom->lines()->make();
                $model->fill($attributes)->save();
                $keep[] = $model->id;
            }

            $this->bom->lines()->whereNotIn('id', $keep)->get()->each->delete();
        });

        $this->loadLines();
        $this->dispatch('toast', message: 'BOM saved.');
    }

    public function approve(BomService $boms): void
    {
        $this->authorize('boms.approve');

        try {
            $this->bom = $boms->approve($this->bom);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->dispatch('toast', message: "Version {$this->bom->version} approved and active.");
    }

    public function newVersion(BomService $boms): void
    {
        $this->authorize('boms.create');

        try {
            $bom = $boms->newVersion($this->bom->item);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->redirectRoute('catalog.boms.edit', $bom, navigate: true);
    }

    public function deleteDraft(): void
    {
        $this->authorize('boms.delete');
        $this->ensureDraft();
        $itemId = $this->bom->item_id;
        $this->bom->delete();

        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('catalog.items.show', $itemId, navigate: true);
    }

    public function openOverride(): void
    {
        $this->authorize('boms.edit');
        $this->reset('match', 'override_line_id', 'override_item_id', 'override_variant_id', 'override_quantity', 'override_wastage', 'override_removes');
        $this->resetValidation();
        $this->showOverride = true;
    }

    public function saveOverride(): void
    {
        $this->authorize('boms.edit');
        $this->ensureDraft();

        $this->validate([
            'match' => ['required', 'regex:/^(value|variant):\d+$/'],
            'override_line_id' => ['nullable', Rule::exists('bom_lines', 'id')->where('bom_id', $this->bom->id)],
            'override_item_id' => [Rule::requiredIf($this->override_line_id === null), 'nullable', Rule::exists('items', 'id')->where('is_active', true), Rule::notIn([$this->bom->item_id])],
            'override_variant_id' => ['nullable', Rule::exists('item_variants', 'id')],
            'override_quantity' => [Rule::requiredIf($this->override_line_id === null), 'nullable', 'numeric', 'gt:0'],
            'override_wastage' => ['nullable', 'numeric', 'min:0', 'max:1000'],
            'override_removes' => ['boolean'],
        ], [], ['match' => 'applies to', 'override_item_id' => 'component', 'override_quantity' => 'quantity']);

        [$kind, $id] = explode(':', $this->match);

        if ($this->override_item_id) {
            try {
                app(BomService::class)->assertNotCircular($this->bom->item_id, [$this->override_item_id]);
            } catch (DocumentException $e) {
                $this->addError('override_item_id', $e->getMessage());

                return;
            }
        }

        $this->bom->overrides()->create([
            'match_attribute_value_id' => $kind === 'value' ? (int) $id : null,
            'match_variant_id' => $kind === 'variant' ? (int) $id : null,
            'bom_line_id' => $this->override_line_id,
            'component_item_id' => $this->override_item_id,
            'component_variant_id' => $this->override_variant_id,
            'quantity' => $this->override_quantity !== '' ? $this->override_quantity : null,
            'unit_id' => $this->override_item_id ? Item::query()->whereKey($this->override_item_id)->value('stock_unit_id') : null,
            'wastage_percent' => $this->override_wastage !== '' ? $this->override_wastage : null,
            'removes_line' => $this->override_line_id !== null && $this->override_removes,
        ]);

        $this->showOverride = false;
    }

    public function removeOverride(int $id): void
    {
        $this->authorize('boms.edit');
        $this->ensureDraft();
        $this->bom->overrides()->findOrFail($id)->delete();
    }

    public function explode(BomExplosion $explosion): void
    {
        $this->previewError = '';
        $this->preview = [];
        $this->validate(['preview_variant_id' => ['required'], 'preview_quantity' => ['required', 'numeric', 'gt:0']]);

        $variant = $this->bom->item->variants()->findOrFail($this->preview_variant_id);

        try {
            $rows = $explosion->explode($variant, (string) $this->preview_quantity, true, $this->bom);
        } catch (DocumentException $e) {
            $this->previewError = $e->getMessage();

            return;
        }

        $stages = Stage::query()->pluck('name', 'id');
        $this->preview = array_map(fn (array $r) => [
            'level' => $r['level'],
            'label' => $r['variant']->fullLabel(),
            'quantity' => $r['quantity'],
            'unit' => $r['variant']->item->stockUnit->symbol,
            'wastage' => $r['wastage_percent'],
            'stage' => $r['stage_id'] ? ($stages[$r['stage_id']] ?? '') : '',
            'optional' => $r['optional'],
            'has_bom' => $r['has_bom'],
        ], $rows);
    }

    private function ensureDraft(): void
    {
        if (! $this->bom->fresh()?->isDraft()) {
            throw new DocumentException("Version {$this->bom->version} is approved and cannot be edited. Create a new version.");
        }
    }

    public function render(): View
    {
        $item = $this->bom->item;
        $componentIds = collect($this->lines)->pluck('component_item_id')->filter()->map(fn ($id) => (int) $id)
            ->push($this->override_item_id)->filter()->unique()->all();

        return view('catalog::livewire.boms.edit', [
            'item' => $item,
            'components' => Item::query()->where('is_active', true)->whereKeyNot($item->id)->orderBy('code')->get()
                ->mapWithKeys(fn (Item $i) => [$i->id => $i->label()]),
            'variantsByItem' => ItemVariant::query()->whereIn('item_id', $componentIds)->where('is_active', true)->orderBy('label')->get()
                ->groupBy('item_id')->map(fn ($vs) => $vs->mapWithKeys(fn (ItemVariant $v) => [$v->id => $v->label !== '' ? $v->label : $v->sku])),
            'units' => Unit::query()->orderBy('name')->pluck('symbol', 'id'),
            'stages' => Stage::query()->ordered()->pluck('name', 'id'),
            'parentVariants' => $item->variants()->where('is_active', true)->orderBy('label')->get()
                ->mapWithKeys(fn (ItemVariant $v) => [$v->id => $v->label !== '' ? $v->label : $v->sku]),
            'matchOptions' => $this->matchOptions($item),
            'overrides' => $this->bom->overrides()->with(['matchAttributeValue.attribute', 'matchVariant', 'line.componentItem', 'componentItem', 'componentVariant'])->get(),
            'savedLines' => $this->bom->lines()->with('componentItem')->get(),
            'editable' => $this->bom->isDraft() && (auth()->user()?->can('boms.edit') ?? false),
        ])->title("BOM {$item->code} v{$this->bom->version}");
    }

    /**
     * @return array<string, string>
     */
    private function matchOptions(Item $item): array
    {
        $options = [];
        $values = AttributeValue::query()->with('attribute')
            ->whereIn('id', DB::table('variant_attribute_values')->whereIn('item_variant_id', $item->variants()->select('id'))->select('attribute_value_id'))
            ->get();

        foreach ($values as $value) {
            $options['value:'.$value->id] = "All {$value->attribute->name} = {$value->value}";
        }

        foreach ($item->variants()->where('label', '!=', '')->orderBy('label')->get() as $variant) {
            $options['variant:'.$variant->id] = "Only {$variant->label}";
        }

        return $options;
    }
}
