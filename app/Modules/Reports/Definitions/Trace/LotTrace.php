<?php

namespace App\Modules\Reports\Definitions\Trace;

use App\Modules\Inventory\Models\Lot;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Documents\Reference;
use Illuminate\Support\Facades\DB;

/** Any lot traced back to its sources and forward to its customers. */
class LotTrace extends Report
{
    public function key(): string
    {
        return 'lot-trace';
    }

    public function title(): string
    {
        return 'Lot trace';
    }

    public function group(): string
    {
        return 'Traceability';
    }

    public function description(): string
    {
        return 'Trace a lot back to its source lots and forward to the lots made from it and the customers who received it.';
    }

    public function permissions(): array
    {
        return ['lots.view'];
    }

    public function filters(): array
    {
        return [new Filter('lot', 'Lot number', 'text')];
    }

    public function columns(): array
    {
        return [new Column('direction', 'Direction'), new Column('level', 'Level', 'number'), new Column('lot', 'Lot', linkKey: 'url'), new Column('sku', 'SKU'),
            new Column('origin', 'Origin / document'), new Column('party', 'Supplier / customer'), new Column('quantity', 'Quantity', 'qty'), new Column('date', 'Date', 'date')];
    }

    protected function build(array $filters): iterable
    {
        $lot = trim((string) ($filters['lot'] ?? '')) !== '' ? Lot::query()->with(['variant', 'vendor', 'contractor', 'source'])->where('lot_number', trim((string) $filters['lot']))->first() : null;
        if (! $lot) {
            return collect();
        }
        $rows = collect([$this->row('This lot', 0, $lot, null)]);

        // Back to the sources.
        $frontier = [$lot->id];
        $seen = [$lot->id => true];
        for ($level = 1; $level <= 5 && $frontier !== []; $level++) {
            $links = DB::table('lot_links')->whereIn('lot_id', $frontier)->get();
            $frontier = [];
            foreach ($links as $l) {
                if (isset($seen[$l->source_lot_id])) {
                    continue;
                }
                $seen[$l->source_lot_id] = true;
                $frontier[] = $l->source_lot_id;
                $rows->push($this->row('Source', $level, Lot::query()->with(['variant', 'vendor', 'contractor', 'source'])->findOrFail($l->source_lot_id), $l->quantity));
            }
        }

        // Forward to lots made from it, and to customers.
        $frontier = [$lot->id];
        $all = [$lot->id];
        for ($level = 1; $level <= 5 && $frontier !== []; $level++) {
            $links = DB::table('lot_links')->whereIn('source_lot_id', $frontier)->get();
            $frontier = [];
            foreach ($links as $l) {
                if (in_array($l->lot_id, $all, true)) {
                    continue;
                }
                $all[] = $frontier[] = $l->lot_id;
                $rows->push($this->row('Used in', $level, Lot::query()->with(['variant', 'vendor', 'contractor', 'source'])->findOrFail($l->lot_id), $l->quantity));
            }
        }
        foreach (DB::table('delivery_allocations as a')->join('delivery_lines as dl', 'dl.id', '=', 'a.delivery_line_id')->join('deliveries as d', 'd.id', '=', 'dl.delivery_id')
            ->join('customers as c', 'c.id', '=', 'd.customer_id')->join('lots as l', 'l.id', '=', 'a.lot_id')->join('item_variants as v', 'v.id', '=', 'dl.item_variant_id')
            ->whereIn('a.lot_id', $all)->where('d.status', 'dispatched')
            ->get(['l.id as lot_id', 'l.lot_number', 'v.sku', 'd.number', 'c.name', 'a.quantity', 'd.delivery_date']) as $r) {
            $rows->push(['direction' => 'Delivered', 'level' => null, 'lot' => $r->lot_number, 'url' => route('inventory.lots.show', $r->lot_id), 'sku' => $r->sku,
                'origin' => $r->number, 'party' => $r->name, 'quantity' => $r->quantity, 'date' => $r->delivery_date]);
        }

        return $rows;
    }

    /** @return array<string, mixed> */
    private function row(string $direction, int $level, Lot $lot, ?string $qty): array
    {
        return ['direction' => $direction, 'level' => $level ?: null, 'lot' => $lot->lot_number, 'url' => route('inventory.lots.show', $lot), 'sku' => $lot->variant->sku,
            'origin' => (Lot::ORIGINS[$lot->origin] ?? $lot->origin).($lot->source_type ? ' · '.Reference::label($lot->source) : ''),
            'party' => $lot->vendor->name ?? $lot->contractor?->name, 'quantity' => $qty, 'date' => $lot->received_date];
    }
}
