<?php

namespace App\Modules\Catalog\Livewire\Items;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Services\VariantGenerator;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Variants of one item: pick attribute values, generate every combination,
 * then edit or remove individual variants.
 */
class Variants extends Component
{
    #[Locked]
    public Item $item;

    /** @var array<int, list<int>> attribute id => selected value ids */
    public array $selected = [];

    public bool $showGenerator = false;

    public bool $showEdit = false;

    #[Locked]
    public ?int $editingId = null;

    public string $sku = '';

    public string $barcode = '';

    public string $price_override = '';

    public string $cost_override = '';

    public string $min_stock = '';

    public bool $is_active = true;

    public function mount(Item $item): void
    {
        $this->item = $item;
    }

    public function openGenerator(): void
    {
        $this->authorize('items.edit');

        // Preselect the values already used by this item's variants.
        $this->selected = [];
        foreach ($this->item->variants()->with('attributeValues')->get() as $variant) {
            foreach ($variant->attributeValues as $value) {
                $this->selected[$value->attribute_id][] = $value->id;
            }
        }
        $this->selected = array_map(fn ($ids) => array_values(array_unique($ids)), $this->selected);
        $this->showGenerator = true;
    }

    public function generate(VariantGenerator $generator): void
    {
        $this->authorize('items.edit');

        $clean = [];
        foreach ($this->selected as $attributeId => $ids) {
            $ids = array_values(array_filter(array_map('intval', (array) $ids)));
            if ($ids !== []) {
                $clean[(int) $attributeId] = $ids;
            }
        }

        $created = $generator->generate($this->item, $clean);
        $this->showGenerator = false;
        $this->dispatch('toast', message: $created === 0 ? 'No new combinations.' : "{$created} variant(s) created. Remove any you don't need.");
    }

    public function edit(int $id): void
    {
        $variant = $this->item->variants()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $variant->id;
        $this->sku = $variant->sku;
        $this->barcode = (string) $variant->barcode;
        $this->price_override = (string) $variant->price_override;
        $this->cost_override = auth()->user()?->can('cost.view') ? (string) $variant->cost_override : '';
        $this->min_stock = (string) $variant->min_stock;
        $this->is_active = $variant->is_active;
        $this->showEdit = true;
    }

    public function save(): void
    {
        $this->authorize('items.edit');
        $variant = $this->item->variants()->findOrFail($this->editingId);
        $canSeeCost = auth()->user()?->can('cost.view') ?? false;

        $data = $this->validate([
            'sku' => ['required', 'string', 'max:60', Rule::unique('item_variants', 'sku')->ignore($variant->id)],
            'barcode' => ['nullable', 'string', 'max:60', Rule::unique('item_variants', 'barcode')->ignore($variant->id)],
            'price_override' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'cost_override' => $canSeeCost ? ['nullable', 'numeric', 'min:0', 'decimal:0,4'] : ['nullable'],
            'min_stock' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'is_active' => ['boolean'],
        ]);

        $data = array_map(fn ($v) => $v === '' ? null : $v, $data);
        if (! $canSeeCost) {
            unset($data['cost_override']);
        }

        if (! $data['is_active'] && $this->item->variants()->where('is_active', true)->whereKeyNot($variant->id)->doesntExist()) {
            $this->addError('is_active', 'An item needs at least one active variant.');

            return;
        }

        $variant->update($data);
        $this->showEdit = false;
        $this->dispatch('toast', message: "Variant {$variant->sku} saved.");
    }

    public function remove(int $id): void
    {
        $this->authorize('items.edit');
        $variant = $this->item->variants()->findOrFail($id);

        if ($this->item->variants()->count() <= 1) {
            $this->dispatch('toast', type: 'error', message: 'An item must keep at least one variant.');

            return;
        }

        try {
            // Unused variants are removed completely so the combination can be generated again.
            if ($variant->isInUse()) {
                throw new DocumentException('This variant is used in transactions and can only be deactivated.');
            }
            $variant->attributeValues()->detach();
            $variant->forceDelete();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    public function render(): View
    {
        return view('catalog::livewire.items.variants', [
            'variants' => $this->item->variants()->with('attributeValues.attribute')->orderBy('label')->get(),
            'attributeList' => Attribute::query()->with('values')->orderBy('sort_order')->orderBy('name')->get(),
            'canSeeCost' => auth()->user()?->can('cost.view') ?? false,
            'canEdit' => auth()->user()?->can('items.edit') ?? false,
        ]);
    }
}
