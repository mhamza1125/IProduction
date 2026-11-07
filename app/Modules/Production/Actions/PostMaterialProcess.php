<?php

namespace App\Modules\Production\Actions;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\Lot;
use App\Modules\Inventory\Models\LotLink;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\Production\Models\MaterialProcess;
use App\Modules\Production\Models\MaterialProcessLine;
use App\Modules\Production\Services\ProductionAccounting;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Material Processing (specification, Module 8): material converted into
 * other material. Posting removes the input lots, creates one output lot per
 * output line linked to every input lot, and posts the labor or contractor
 * cost (Dr Inventory, Cr Wages / Contractor Payable).
 *
 * Output cost = input cost + labor cost − recoverable value, shared over the
 * outputs by actual quantity. Losses are recorded for yield; their cost stays
 * in the output (allowed loss).
 */
class PostMaterialProcess
{
    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly StockLedger $stock,
        private readonly ProductionAccounting $accounting,
        private readonly DocumentNumber $numbers,
    ) {}

    /**
     * @param  array<string, mixed>  $header
     * @param  list<array<string, mixed>>  $inputs  item_variant_id, lot_id, quantity
     * @param  list<array<string, mixed>>  $outputs  item_variant_id, expected_qty, quantity, location_id
     * @param  list<array<string, mixed>>  $losses  quantity, loss_type, reason
     */
    public function save(?MaterialProcess $process, array $header, array $inputs, array $outputs, array $losses): MaterialProcess
    {
        if ($process && ! $process->isDraft()) {
            throw new DocumentException("{$process->number} is {$process->status} and cannot be edited.");
        }
        if ($inputs === [] || $outputs === []) {
            throw new DocumentException('Add at least one input and one output.');
        }
        foreach ([...$inputs, ...$outputs] as $l) {
            if (Decimal::compare((string) $l['quantity'], '0') <= 0) {
                throw new DocumentException('Quantities must be above zero.');
            }
        }
        if (($header['worker_type'] ?? null) === 'employee' && empty($header['employee_id']) || ($header['worker_type'] ?? null) === 'contractor' && empty($header['contractor_id'])) {
            throw new DocumentException('Choose the employee or contractor.');
        }

        return DB::transaction(function () use ($process, $header, $inputs, $outputs, $losses) {
            $process ??= new MaterialProcess(['number' => $this->numbers->next('material_process', Carbon::parse($header['process_date'])), 'created_by' => auth()->id()]);
            $type = $header['worker_type'] ?? null ?: null;
            $process->fill([
                'process_date' => $header['process_date'], 'process_name' => $header['process_name'], 'worker_type' => $type,
                'employee_id' => $type === 'employee' ? $header['employee_id'] : null, 'contractor_id' => $type === 'contractor' ? $header['contractor_id'] : null,
                'sales_order_line_id' => $header['sales_order_line_id'] ?? null ?: null, 'ptc_id' => $header['ptc_id'] ?? null ?: null,
                'labor_cost' => ($header['labor_cost'] ?? '') === '' ? null : $header['labor_cost'],
                'recoverable_value' => ($header['recoverable_value'] ?? '') === '' ? null : $header['recoverable_value'],
                'notes' => $header['notes'] ?? null ?: null,
            ])->save();
            $process->lines()->delete();
            $i = 0;
            foreach ($inputs as $l) {
                $process->lines()->create(['kind' => 'input', 'item_variant_id' => $l['item_variant_id'], 'lot_id' => $l['lot_id'] ?? null ?: null, 'quantity' => $l['quantity'], 'sort_order' => $i++]);
            }
            foreach ($outputs as $l) {
                $process->lines()->create(['kind' => 'output', 'item_variant_id' => $l['item_variant_id'], 'expected_qty' => ($l['expected_qty'] ?? '') === '' ? null : $l['expected_qty'],
                    'quantity' => $l['quantity'], 'location_id' => $l['location_id'] ?? null ?: null, 'sort_order' => $i++]);
            }
            foreach ($losses as $l) {
                if (Decimal::compare((string) ($l['quantity'] ?: '0'), '0') > 0) {
                    $process->lines()->create(['kind' => 'loss', 'quantity' => $l['quantity'], 'loss_type' => $l['loss_type'] ?? 'wastage', 'reason' => $l['reason'] ?? null, 'sort_order' => $i++]);
                }
            }

            return $process;
        });
    }

    public function __invoke(MaterialProcess $process, ?string $overrideReason = null): MaterialProcess
    {
        return $this->workflow->post($process, function (MaterialProcess $p) {
            $date = $p->process_date;
            $lines = $p->lines()->get();

            $inputCost = BigDecimal::zero();
            $inputLots = [];
            foreach ($lines->where('kind', 'input') as $in) {
                foreach ($this->stock->allocate((int) $in->item_variant_id, $in->quantity, null, StockStatus::AVAILABLE, null, null, $in->lot_id) as $a) {
                    $this->stock->remove($a['bucket'], $a['quantity'], new StockSource($p, $date, lineId: $in->id));
                    $cost = Lot::query()->whereKey($a['bucket']->lotId)->value('unit_cost') ?? '0';
                    $inputCost = $inputCost->plus(Decimal::of($a['quantity'])->multipliedBy($cost));
                    $inputLots[$a['bucket']->lotId] = Decimal::add($inputLots[$a['bucket']->lotId] ?? '0', $a['quantity'], 4);
                }
                $in->update(['total_cost' => (string) $inputCost->toScale(2, RoundingMode::HalfUp)]);
            }

            $labor = Decimal::of($p->labor_cost ?? '0');
            $worker = $p->employee ?? $p->contractor;
            if ($labor->isPositive()) {
                if ($worker === null) {
                    throw new DocumentException('Choose who the labor cost is payable to.');
                }
                $this->accounting->labor($worker, (string) $labor->toScale(2), $date, $p, "{$p->number} {$p->process_name}");
            }

            $net = $inputCost->plus($labor)->minus(Decimal::of($p->recoverable_value ?? '0'));
            if ($net->isNegative()) {
                throw new DocumentException('The recoverable value is more than the input and labor cost.');
            }
            $outputs = $lines->where('kind', 'output')->values();
            $totalOut = Decimal::of(Decimal::sum($outputs->pluck('quantity'), 4));
            $unitCost = $net->dividedBy($totalOut, 4, RoundingMode::HalfUp);

            // The recoverable value (off-cuts to be sold...) is taken out of the
            // output cost. No journal: it stays in Inventory until the off-cuts
            // are sold or written off. (Accountant to confirm.)

            $allocated = BigDecimal::zero();
            foreach ($outputs as $i => $out) {
                /** @var MaterialProcessLine $out */
                $lineCost = $i === $outputs->count() - 1 ? $net->minus($allocated) : $unitCost->multipliedBy($out->quantity)->toScale(2, RoundingMode::HalfUp);
                $allocated = $allocated->plus($lineCost);
                $lot = $this->stock->createLot((int) $out->item_variant_id, 'made', (string) $lineCost->dividedBy($out->quantity, 4, RoundingMode::HalfUp), $date, $p, ['notes' => $p->process_name]);
                foreach ($inputLots as $lotId => $qty) {
                    LotLink::query()->create(['lot_id' => $lot->id, 'source_lot_id' => $lotId, 'quantity' => $qty, 'document_type' => $p->getMorphClass(), 'document_id' => $p->id]);
                }
                $this->stock->receive(new StockBucket((int) $out->item_variant_id, null, StockStatus::AVAILABLE, $out->location_id ?? Location::mainStore()->id, $lot->id), $out->quantity, new StockSource($p, $date, lineId: $out->id));
                $out->update(['lot_id' => $lot->id, 'total_cost' => (string) $lineCost->toScale(2, RoundingMode::HalfUp)]);
            }

            $p->forceFill(['input_cost' => (string) $inputCost->toScale(2, RoundingMode::HalfUp), 'output_unit_cost' => (string) $unitCost])->save();
        }, $overrideReason);
    }

    /**
     * @return array{expected: string|null, actual: string, wastage_percent: string|null, input: string}
     */
    public function yield(MaterialProcess $process): array
    {
        $lines = $process->lines;
        $expected = $lines->where('kind', 'output')->whereNotNull('expected_qty')->isEmpty() ? null : Decimal::sum($lines->where('kind', 'output')->pluck('expected_qty'), 4);
        $actual = Decimal::sum($lines->where('kind', 'output')->pluck('quantity'), 4);
        $input = Decimal::sum($lines->where('kind', 'input')->pluck('quantity'), 4);
        $loss = Decimal::sum($lines->where('kind', 'loss')->pluck('quantity'), 4);

        return [
            'expected' => $expected,
            'actual' => $actual,
            'input' => $input,
            'wastage_percent' => Decimal::compare($input, '0') > 0 ? Decimal::div(Decimal::mul($loss, '100', 4), $input, 2) : null,
        ];
    }
}
