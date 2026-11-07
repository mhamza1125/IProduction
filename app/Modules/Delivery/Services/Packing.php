<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Delivery\Models\Carton;
use App\Modules\Delivery\Models\CartonLine;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\PackingList;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Packing list (specification, Module 9): one per delivery; cartons may mix
 * lines from several orders; packed total must match each delivery line
 * before the delivery can be marked Packed.
 */
class Packing
{
    /**
     * Adds a carton (and $repeat - 1 identical ones).
     *
     * @param  array<string, mixed>  $carton  carton_type, length, width, height, gross_weight, net_weight, marks, notes
     * @param  array<int, string>  $contents  delivery line id => quantity
     * @return list<Carton>
     */
    public function addCarton(Delivery $delivery, array $carton, array $contents, int $repeat = 1): array
    {
        $contents = array_filter($contents, fn ($q) => Decimal::compare((string) $q, '0') > 0);
        if ($contents === []) {
            throw new DocumentException('Put at least one line in the carton.');
        }
        if ($repeat < 1 || $repeat > 500) {
            throw new DocumentException('Repeat between 1 and 500 cartons.');
        }

        return DB::transaction(function () use ($delivery, $carton, $contents, $repeat) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($delivery->status !== 'allocated') {
                throw new DocumentException('Cartons can be added while the delivery is allocated and not yet packed.');
            }
            $list = PackingList::query()->firstOrCreate(['delivery_id' => $delivery->id]);
            $lines = $delivery->lines()->get()->keyBy('id');
            $packed = $this->packedByLine($list);

            foreach ($contents as $lineId => $qty) {
                $line = $lines[$lineId] ?? throw new DocumentException('That line is not on this delivery.');
                $after = Decimal::add($packed[$lineId] ?? '0', Decimal::mul((string) $qty, (string) $repeat, 4), 4);
                if (Decimal::compare($after, $line->quantity) > 0) {
                    throw new DocumentException("Packing more {$line->variant->sku} than the {$line->quantity} on the delivery.");
                }
            }

            $next = (int) $list->cartons()->max('carton_number') + 1;
            $created = [];
            for ($i = 0; $i < $repeat; $i++) {
                $c = $list->cartons()->create(['carton_number' => $next + $i] + array_map(fn ($v) => $v === '' ? null : $v, array_intersect_key($carton, array_flip(['carton_type', 'length', 'width', 'height', 'gross_weight', 'net_weight', 'marks', 'notes']))));
                foreach ($contents as $lineId => $qty) {
                    $c->lines()->create(['delivery_line_id' => $lineId, 'quantity' => $qty]);
                }
                $created[] = $c;
            }

            return $created;
        });
    }

    public function removeCarton(Carton $carton): void
    {
        $list = PackingList::query()->with('delivery')->findOrFail($carton->packing_list_id);
        if ($list->delivery->status !== 'allocated') {
            throw new DocumentException('Cartons can only be removed before packing is complete.');
        }
        $carton->delete();
    }

    public function complete(Delivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($delivery->status !== 'allocated') {
                throw new DocumentException("{$delivery->number} is {$delivery->status}.");
            }
            $list = PackingList::query()->firstOrCreate(['delivery_id' => $delivery->id]);
            $packed = $this->packedByLine($list);
            foreach ($delivery->lines()->with('variant')->get() as $line) {
                if (Decimal::compare($packed[$line->id] ?? '0', $line->quantity) !== 0) {
                    throw new DocumentException("{$line->variant->sku}: packed ".Decimal::round($packed[$line->id] ?? '0', 4)." of {$line->quantity}. Packing must match the delivery.");
                }
            }
            $list->update(['completed_at' => now()]);
            $delivery->forceFill(['status' => 'packed'])->save();
            activity('documents')->performedOn($delivery)->event('packed')->log('Packed');
        });
    }

    public function reopen(Delivery $delivery): void
    {
        DB::transaction(function () use ($delivery) {
            $delivery = Delivery::query()->lockForUpdate()->findOrFail($delivery->id);
            if ($delivery->status !== 'packed') {
                throw new DocumentException("{$delivery->number} is {$delivery->status}.");
            }
            $delivery->packingList?->update(['completed_at' => null]);
            $delivery->forceFill(['status' => 'allocated'])->save();
        });
    }

    /** @return array<int, string> delivery line id => packed quantity */
    public function packedByLine(PackingList $list): array
    {
        return CartonLine::query()->whereIn('carton_id', $list->cartons()->select('id'))->get()
            ->groupBy('delivery_line_id')->map(fn ($rows) => Decimal::sum($rows->pluck('quantity'), 4))->all();
    }

    /** @return array{cartons: int, pieces: string, gross: string, net: string} */
    public function totals(PackingList $list): array
    {
        /** @var Collection<int, Carton> $cartons */
        $cartons = $list->cartons()->with('lines')->get();

        return [
            'cartons' => $cartons->count(),
            'pieces' => Decimal::sum($cartons->flatMap->lines->pluck('quantity'), 4),
            'gross' => Decimal::sum($cartons->pluck('gross_weight'), 3),
            'net' => Decimal::sum($cartons->pluck('net_weight'), 3),
        ];
    }
}
