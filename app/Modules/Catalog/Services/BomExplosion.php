<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Bom;
use App\Modules\Catalog\Models\BomOverride;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Services\UnitConverter;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Brick\Math\RoundingMode;

/**
 * Works out the components needed to make a quantity of a variant.
 *
 * Required quantity = quantity × component quantity ÷ BOM output quantity
 *                     × (1 + wastage % ÷ 100), converted to the component's stock unit.
 * Whole-unit stock units (pieces, pairs, boxes) are rounded up.
 *
 * Multi-level: components that can be made and have an active BOM are
 * exploded further; each row says its level so planning can decide
 * whether to make or buy them.
 */
class BomExplosion
{
    public function __construct(private readonly UnitConverter $units) {}

    /**
     * @param  list<int>  $path  item ids above this level (for loop detection)
     * @return list<array{level: int, variant: ItemVariant, quantity: string, wastage_percent: string, stage_id: int|null, optional: bool, has_bom: bool, parent_variant_id: int}>
     */
    public function explode(ItemVariant $variant, string $quantity, bool $multiLevel = true, ?Bom $bom = null, int $level = 1, array $path = []): array
    {
        $variant->loadMissing('item');
        $bom ??= $variant->item->activeBom();

        if ($bom === null) {
            throw new DocumentException("{$variant->fullLabel()} has no approved BOM.");
        }

        if (in_array($variant->item_id, $path, true)) {
            throw new DocumentException('Circular BOM found while exploding '.$variant->fullLabel().'.');
        }

        $rows = [];

        foreach ($this->resolveLines($bom, $variant) as $line) {
            $component = $line['variant'];
            $item = $component->item;

            $required = Decimal::of($quantity)
                ->multipliedBy(Decimal::of($line['quantity']))
                ->dividedBy(Decimal::of($bom->output_quantity), 10, RoundingMode::HalfUp)
                ->multipliedBy(Decimal::of('1')->plus(Decimal::of($line['wastage_percent'])->dividedBy('100', 10, RoundingMode::HalfUp)));

            $inStockUnit = $this->units->convert((string) $required->toScale(10, RoundingMode::HalfUp), $line['unit_id'], $item->stock_unit_id, $item->id, 10);
            $stockUnit = $item->stockUnit;
            $rounded = $stockUnit->allows_decimal
                ? Decimal::round($inStockUnit, Decimal::QUANTITY)
                : (string) Decimal::of($inStockUnit)->toScale(0, RoundingMode::Ceiling)->toScale(Decimal::QUANTITY);

            $childBom = $item->can_make ? $item->activeBom() : null;

            $rows[] = [
                'level' => $level,
                'variant' => $component,
                'quantity' => $rounded,
                'wastage_percent' => $line['wastage_percent'],
                'stage_id' => $line['stage_id'],
                'optional' => $line['optional'],
                'has_bom' => $childBom !== null,
                'parent_variant_id' => $variant->id,
            ];

            if ($multiLevel && $childBom !== null) {
                array_push($rows, ...$this->explode($component, $rounded, true, $childBom, $level + 1, [...$path, $variant->item_id]));
            }
        }

        return $rows;
    }

    /**
     * BOM lines after applying the overrides that match this variant.
     *
     * @return list<array{variant: ItemVariant, quantity: string, unit_id: int, wastage_percent: string, stage_id: int|null, optional: bool}>
     */
    public function resolveLines(Bom $bom, ItemVariant $variant): array
    {
        $bom->loadMissing([
            'lines.componentItem.variants', 'lines.componentItem.stockUnit', 'lines.componentVariant.item.stockUnit',
            'overrides.componentItem.variants', 'overrides.componentItem.stockUnit', 'overrides.componentVariant.item.stockUnit',
        ]);
        $valueIds = $variant->attributeValues()->pluck('attribute_values.id')->all();

        // Attribute-value overrides first, then variant-specific ones, so the latter win.
        $overrides = $bom->overrides
            ->filter(fn (BomOverride $o) => ($o->match_variant_id !== null && $o->match_variant_id === $variant->id)
                || ($o->match_attribute_value_id !== null && in_array($o->match_attribute_value_id, $valueIds, true)))
            ->sortBy(fn (BomOverride $o) => $o->match_variant_id !== null ? 1 : 0);

        $lines = [];

        foreach ($bom->lines as $line) {
            $lines[$line->id] = [
                'item' => $line->componentItem,
                'variant' => $line->componentVariant,
                'quantity' => $line->quantity,
                'unit_id' => $line->unit_id,
                'wastage_percent' => $line->wastage_percent,
                'stage_id' => $line->stage_id,
                'optional' => $line->is_optional,
            ];
        }

        $extra = [];

        foreach ($overrides as $o) {
            if ($o->bom_line_id !== null) {
                if (! isset($lines[$o->bom_line_id])) {
                    continue;
                }

                if ($o->removes_line) {
                    unset($lines[$o->bom_line_id]);

                    continue;
                }

                $current = &$lines[$o->bom_line_id];
                if ($o->component_item_id !== null) {
                    $current['item'] = $o->componentItem;
                    $current['variant'] = $o->componentVariant;
                } elseif ($o->component_variant_id !== null) {
                    $current['variant'] = $o->componentVariant;
                }
                $current['quantity'] = $o->quantity ?? $current['quantity'];
                $current['unit_id'] = $o->unit_id ?? $current['unit_id'];
                $current['wastage_percent'] = $o->wastage_percent ?? $current['wastage_percent'];
                $current['stage_id'] = $o->stage_id ?? $current['stage_id'];
                unset($current);
            } elseif ($o->component_item_id !== null && $o->quantity !== null) {
                $extra[] = [
                    'item' => $o->componentItem,
                    'variant' => $o->componentVariant,
                    'quantity' => $o->quantity,
                    'unit_id' => $o->unit_id ?? $o->componentItem->stock_unit_id,
                    'wastage_percent' => $o->wastage_percent ?? '0',
                    'stage_id' => $o->stage_id,
                    'optional' => false,
                ];
            }
        }

        return array_map(function (array $line) use ($variant) {
            $line['variant'] ??= $this->onlyVariant($line['item'], $variant);
            unset($line['item']);

            return $line;
        }, [...array_values($lines), ...$extra]);
    }

    private function onlyVariant(Item $item, ItemVariant $parent): ItemVariant
    {
        $variants = $item->variants->where('is_active', true);

        if ($variants->count() === 1) {
            $only = $variants->first();
            $only->setRelation('item', $item);

            return $only;
        }

        throw new DocumentException("The BOM for {$parent->fullLabel()} uses {$item->code}, which has several variants. Choose the variant on the BOM line or with an override.");
    }
}
