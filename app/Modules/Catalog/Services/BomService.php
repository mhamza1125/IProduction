<?php

namespace App\Modules\Catalog\Services;

use App\Modules\Catalog\Models\Bom;
use App\Modules\Catalog\Models\BomLine;
use App\Modules\Catalog\Models\Item;
use App\Support\Documents\DocumentException;
use Illuminate\Support\Facades\DB;

/**
 * BOM versions: drafts are edited; approving makes a version active and
 * supersedes the previous one. A PTC copies the active BOM when created.
 */
class BomService
{
    /**
     * New draft version, copied from the latest version when there is one.
     */
    public function newVersion(Item $item): Bom
    {
        if (! $item->can_make) {
            throw new DocumentException("{$item->code} is not marked as 'can be made', so it has no BOM.");
        }

        if ($item->boms()->where('status', Bom::DRAFT)->exists()) {
            throw new DocumentException('This item already has a draft BOM version. Finish or delete it first.');
        }

        return DB::transaction(function () use ($item) {
            $latest = $item->boms()->orderByDesc('version')->with(['lines', 'overrides'])->first();

            $bom = Bom::query()->create([
                'item_id' => $item->id,
                'version' => ($latest->version ?? 0) + 1,
                'effective_date' => now()->toDateString(),
                'output_quantity' => $latest->output_quantity ?? '1',
                'notes' => $latest?->notes,
                'created_by' => auth()->id(),
            ]);

            if ($latest) {
                $lineMap = [];
                foreach ($latest->lines as $line) {
                    $copy = $bom->lines()->create($line->only([
                        'component_item_id', 'component_variant_id', 'quantity', 'unit_id', 'wastage_percent', 'stage_id', 'is_optional', 'notes', 'sort_order',
                    ]));
                    $lineMap[$line->id] = $copy->id;
                }

                foreach ($latest->overrides as $override) {
                    $bom->overrides()->create(array_merge($override->only([
                        'match_attribute_value_id', 'match_variant_id', 'component_item_id', 'component_variant_id',
                        'quantity', 'unit_id', 'wastage_percent', 'stage_id', 'removes_line', 'notes',
                    ]), ['bom_line_id' => $override->bom_line_id ? ($lineMap[$override->bom_line_id] ?? null) : null]));
                }
            }

            return $bom;
        });
    }

    public function approve(Bom $bom): Bom
    {
        return DB::transaction(function () use ($bom) {
            /** @var Bom $bom */
            $bom = Bom::query()->lockForUpdate()->findOrFail($bom->id);

            if (! $bom->isDraft()) {
                throw new DocumentException("Version {$bom->version} is already approved.");
            }

            $lines = $bom->lines()->with('componentItem')->get();

            if ($lines->isEmpty()) {
                throw new DocumentException('Add at least one component before approving.');
            }

            foreach ($lines as $line) {
                if (! $line->componentItem->is_active) {
                    throw new DocumentException("Component {$line->componentItem->code} is inactive.");
                }
            }

            $this->assertNotCircular($bom->item_id, $lines->pluck('component_item_id')->all());

            Bom::query()->where('item_id', $bom->item_id)->where('is_active', true)
                ->update(['is_active' => false, 'status' => Bom::SUPERSEDED]);

            $bom->forceFill([
                'status' => Bom::APPROVED,
                'is_active' => true,
                'approved_by' => auth()->id(),
                'approved_at' => now(),
            ])->saveQuietly();

            activity('boms')->performedOn($bom)->event('approved')->log("Approved BOM version {$bom->version}");

            return $bom;
        });
    }

    /**
     * Throws if adding these components to $parentItemId would make an item
     * (directly or through other BOMs) a component of itself.
     *
     * @param  list<int>  $componentItemIds
     */
    public function assertNotCircular(int $parentItemId, array $componentItemIds): void
    {
        $path = $this->findPath($parentItemId, $componentItemIds, []);

        if ($path !== null) {
            $codes = Item::withTrashed()->whereIn('id', $path)->pluck('code', 'id');
            $chain = implode(' → ', array_map(fn ($id) => $codes[$id] ?? $id, [$parentItemId, ...$path]));

            throw new DocumentException("Circular BOM: {$chain}.");
        }
    }

    /**
     * Depth-first search through the latest BOM of every component.
     *
     * @param  list<int>  $componentItemIds
     * @param  list<int>  $visited
     * @return list<int>|null the path back to the parent, or null
     */
    private function findPath(int $target, array $componentItemIds, array $visited): ?array
    {
        foreach (array_unique($componentItemIds) as $componentId) {
            if ($componentId === $target) {
                return [$componentId];
            }

            if (in_array($componentId, $visited, true)) {
                continue;
            }

            $children = BomLine::query()
                ->whereIn('bom_id', Bom::query()->where('item_id', $componentId)->whereIn('status', [Bom::DRAFT, Bom::APPROVED])->select('id'))
                ->pluck('component_item_id')->map(fn ($id) => (int) $id)->all();

            $path = $this->findPath($target, $children, [...$visited, $componentId]);

            if ($path !== null) {
                return [$componentId, ...$path];
            }
        }

        return null;
    }
}
