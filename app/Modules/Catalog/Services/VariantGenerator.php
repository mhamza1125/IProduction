<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\AttributeValue;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Creates every combination of the chosen attribute values as variants.
 * Existing combinations are kept; users then remove the ones they don't need.
 */
class VariantGenerator
{
    /**
     * @param  array<int, list<int>>  $valueIdsByAttribute  attribute id => chosen value ids
     * @return int Number of variants created
     */
    public function generate(Item $item, array $valueIdsByAttribute): int
    {
        $valueIdsByAttribute = array_filter($valueIdsByAttribute, fn (array $ids) => $ids !== []);

        if ($valueIdsByAttribute === []) {
            return 0;
        }

        return DB::transaction(function () use ($item, $valueIdsByAttribute) {
            // Remember which attributes this item varies by.
            $item->attributes()->sync(collect(array_keys($valueIdsByAttribute))->mapWithKeys(fn ($id, $i) => [$id => ['sort_order' => $i]])->all());

            $values = AttributeValue::query()->with('attribute')->whereIn('id', array_merge(...array_values($valueIdsByAttribute)))->get()->keyBy('id');
            $created = 0;

            foreach ($this->combinations(array_values($valueIdsByAttribute)) as $combination) {
                $key = self::key($combination);

                if (ItemVariant::withTrashed()->where('item_id', $item->id)->where('combination_key', $key)->exists()) {
                    continue;
                }

                $parts = array_map(fn (int $id) => $values[$id]->value, $combination);

                $variant = $item->variants()->create([
                    'sku' => $this->uniqueSku($item->code.'-'.implode('-', array_map(fn ($p) => Str::upper(Str::slug($p, '')), $parts))),
                    'combination_key' => $key,
                    'label' => implode(' / ', $parts),
                ]);
                $variant->attributeValues()->sync($combination);
                $created++;
            }

            // The plain variant made with the item is replaced by the combinations, unless it is already used.
            $plain = $item->variants()->where('combination_key', '')->first();
            if ($plain !== null && $created > 0 && ! $plain->isInUse()) {
                $plain->forceDelete();
            }

            return $created;
        });
    }

    /**
     * @param  list<int>  $valueIds
     */
    public static function key(array $valueIds): string
    {
        sort($valueIds);

        return implode('-', $valueIds);
    }

    /**
     * Cartesian product: [[1,2],[5,6]] → [[1,5],[1,6],[2,5],[2,6]].
     *
     * @param  list<list<int>>  $sets
     * @return list<list<int>>
     */
    private function combinations(array $sets): array
    {
        $result = [[]];

        foreach ($sets as $set) {
            $next = [];
            foreach ($result as $prefix) {
                foreach ($set as $value) {
                    $next[] = [...$prefix, (int) $value];
                }
            }
            $result = $next;
        }

        return $result;
    }

    private function uniqueSku(string $base): string
    {
        $sku = Str::limit($base, 55, '');
        $n = 1;

        while (ItemVariant::withTrashed()->where('sku', $sku)->exists()) {
            $sku = Str::limit($base, 52, '').'-'.++$n;
        }

        return $sku;
    }
}
