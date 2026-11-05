<?php

namespace App\Modules\Catalog\Livewire\Attributes;

use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\AttributeValue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Attributes')]
class Index extends Component
{
    public string $newAttribute = '';

    /** @var array<int, string> attribute id => value being typed */
    public array $newValue = [];

    public function addAttribute(): void
    {
        $this->authorize('attributes.create');
        $this->validate(['newAttribute' => ['required', 'string', 'max:50', Rule::unique('attributes', 'name')]], [], ['newAttribute' => 'attribute']);

        Attribute::query()->create(['name' => trim($this->newAttribute), 'sort_order' => (int) Attribute::query()->max('sort_order') + 10]);
        $this->newAttribute = '';
    }

    public function addValue(int $attributeId): void
    {
        $this->authorize('attributes.edit');
        $attribute = Attribute::query()->findOrFail($attributeId);
        $value = trim($this->newValue[$attributeId] ?? '');

        validator(['value' => $value], [
            'value' => ['required', 'string', 'max:50', Rule::unique('attribute_values', 'value')->where('attribute_id', $attribute->id)],
        ])->validate();

        $attribute->values()->create(['value' => $value, 'sort_order' => (int) $attribute->values()->max('sort_order') + 10]);
        $this->newValue[$attributeId] = '';
    }

    public function removeValue(int $valueId): void
    {
        $this->authorize('attributes.delete');
        $value = AttributeValue::query()->findOrFail($valueId);

        if (DB::table('variant_attribute_values')->where('attribute_value_id', $value->id)->exists()
            || DB::table('bom_overrides')->where('match_attribute_value_id', $value->id)->exists()) {
            $this->dispatch('toast', type: 'error', message: "{$value->value} is used by variants or BOM overrides.");

            return;
        }

        $value->delete();
    }

    public function removeAttribute(int $attributeId): void
    {
        $this->authorize('attributes.delete');
        $attribute = Attribute::query()->findOrFail($attributeId);

        if (DB::table('item_attributes')->where('attribute_id', $attribute->id)->exists()) {
            $this->dispatch('toast', type: 'error', message: "{$attribute->name} is used by items.");

            return;
        }

        $attribute->delete();
    }

    public function render(): View
    {
        return view('catalog::livewire.attributes.index', [
            'attributeList' => Attribute::query()->with('values')->orderBy('sort_order')->orderBy('name')->get(),
        ]);
    }
}
