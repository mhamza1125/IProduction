<?php

namespace App\Modules\Purchasing\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceiptLine;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Models\LandedCostAllocation;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Closure;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Landed cost: additional purchase charges added to the goods' cost.
 *
 * Posting spreads the charges over the lines of the chosen goods receipts, by
 * value (default) or by quantity, and for each line's lot:
 *  - per unit = line share ÷ quantity received into the lot;
 *  - the lot's unit cost rises by that amount, so stock still on hand (in any
 *    status, including material issued to production) carries it: Dr Inventory;
 *  - quantity already used or sold cannot carry it any more: Dr Cost of Goods Sold;
 *  - material held by running PTCs: the same amount is added to those PTCs'
 *    cost (hook registered by Production), so their output carries it when released.
 * Credit: the cash/bank account (paid now) or Accounts Payable to the vendor.
 */
class LandedCosts
{
    /**
     * Hooks run for each lot inside the posting transaction; each returns the
     * amount it absorbed (for reporting). Keyed so repeated boots do not register twice.
     *
     * @var array<string, Closure(int, string, LandedCost): string>
     */
    private static array $lotHooks = [];

    public function __construct(
        private readonly DocumentWorkflow $workflow,
        private readonly DocumentNumber $numbers,
        private readonly StockLedger $stock,
        private readonly JournalPoster $journals,
        private readonly MoneyAccounts $accounts,
    ) {}

    /** @param  Closure(int $lotId, string $perUnit, LandedCost $doc): string  $hook */
    public static function afterLotCost(string $key, Closure $hook): void
    {
        self::$lotHooks[$key] = $hook;
    }

    /**
     * @param  array{cost_date: string, vendor_id?: int|null, money_account_id?: int|null, method: string, reference?: string|null, notes?: string|null}  $header
     * @param  list<array{charge_type: string, description?: string|null, amount: string}>  $charges
     * @param  list<int>  $receiptIds
     */
    public function save(?LandedCost $doc, array $header, array $charges, array $receiptIds): LandedCost
    {
        if ($doc !== null && ! $doc->isDraft()) {
            throw new DocumentException("{$doc->number} is not a draft.");
        }
        $charges = array_values(array_filter($charges, fn ($c) => trim($c['amount']) !== ''));
        if ($charges === []) {
            throw new DocumentException('Add at least one charge.');
        }
        foreach ($charges as $c) {
            if (! array_key_exists($c['charge_type'], LandedCost::CHARGE_TYPES) || Decimal::compare((string) $c['amount'], '0') <= 0) {
                throw new DocumentException('Each charge needs a type and an amount above zero.');
            }
        }
        if (! array_key_exists($header['method'], LandedCost::METHODS)) {
            throw new DocumentException('Choose how to spread the charges.');
        }
        if (($header['vendor_id'] ?? null) === null && ($header['money_account_id'] ?? null) === null) {
            throw new DocumentException('Choose the account it was paid from, or the vendor it is payable to.');
        }
        if (($header['money_account_id'] ?? null) !== null && MoneyAccount::query()->whereKey($header['money_account_id'])->value('currency_code') !== 'PKR') {
            throw new DocumentException('Landed costs are in PKR: pay them from a PKR account.');
        }
        $receiptIds = array_values(array_unique(array_map('intval', $receiptIds)));
        if ($receiptIds === []) {
            throw new DocumentException('Choose the goods receipts these charges are for.');
        }
        if (GoodsReceipt::query()->whereIn('id', $receiptIds)->whereNotNull('posted_at')->count() !== count($receiptIds)) {
            throw new DocumentException('Landed costs can only be added to posted goods receipts.');
        }

        return DB::transaction(function () use ($doc, $header, $charges, $receiptIds) {
            $date = Carbon::parse($header['cost_date']);
            $doc ??= new LandedCost(['number' => $this->numbers->next('landed_cost', $date)]);
            $doc->fill([
                'cost_date' => $date, 'vendor_id' => $header['vendor_id'] ?? null, 'money_account_id' => $header['money_account_id'] ?? null,
                'method' => $header['method'], 'reference' => $header['reference'] ?? null, 'notes' => $header['notes'] ?? null,
                'total' => Decimal::sum(array_column($charges, 'amount')),
            ])->save();
            $doc->charges()->delete();
            foreach ($charges as $c) {
                $doc->charges()->create(['charge_type' => $c['charge_type'], 'description' => $c['description'] ?? null, 'amount' => Decimal::round((string) $c['amount'])]);
            }
            $doc->receipts()->sync($receiptIds);

            return $doc;
        });
    }

    public function post(LandedCost $doc, ?string $overrideReason = null): LandedCost
    {
        return $this->workflow->post($doc, function (LandedCost $d) {
            $total = $d->total;
            $lines = $this->lines($d);
            if ($lines->isEmpty()) {
                throw new DocumentException('The chosen receipts have no stock to add the charges to.');
            }

            // Spread over the lines; the last line takes the rounding remainder.
            $basisTotal = Decimal::sum($lines->pluck('basis'), 4);
            if (Decimal::compare($basisTotal, '0') <= 0) {
                throw new DocumentException($d->method === 'value' ? 'The chosen receipts have no value to spread by: spread by quantity instead.' : 'The chosen receipts have no quantity.');
            }
            $left = $total;
            $toStock = '0';
            $toCogs = '0';
            foreach ($lines->values() as $i => $line) {
                $share = $i === $lines->count() - 1 ? $left
                    : (string) Decimal::of($total)->multipliedBy($line['basis'])->dividedBy(Decimal::of($basisTotal), 2, RoundingMode::HalfUp);
                $left = Decimal::sub($left, $share);
                if (Decimal::compare($share, '0') <= 0) {
                    continue;
                }

                $perUnit = (string) Decimal::of($share)->dividedBy(Decimal::of($line['lot_qty']), 4, RoundingMode::HalfUp);
                $lot = $this->stock->addLotCost($line['lot_id'], $perUnit);
                $onHand = Decimal::compare($lot['on_hand'], $line['lot_qty']) > 0 ? $line['lot_qty'] : $lot['on_hand'];
                $stockPart = Decimal::compare($onHand, $line['lot_qty']) === 0 ? $share : Decimal::mul($perUnit, $onHand);
                $production = '0';
                foreach (self::$lotHooks as $hook) {
                    $production = Decimal::add($production, $hook($line['lot_id'], $perUnit, $d));
                }

                LandedCostAllocation::query()->create([
                    'landed_cost_id' => $d->id, 'goods_receipt_line_id' => $line['line_id'], 'lot_id' => $line['lot_id'],
                    'basis' => $line['basis'], 'lot_qty' => $line['lot_qty'], 'on_hand_qty' => $onHand, 'amount' => $share,
                    'to_stock' => $stockPart, 'to_production' => $production, 'to_cogs' => Decimal::sub($share, $stockPart),
                    'unit_cost_before' => $lot['before'], 'unit_cost_after' => $lot['after'],
                ]);
                $toStock = Decimal::add($toStock, $stockPart);
                $toCogs = Decimal::add($toCogs, Decimal::sub($share, $stockPart));
            }

            $entry = (new JournalEntry($d->cost_date, 'PKR', "{$d->number} landed cost".($d->reference ? " ({$d->reference})" : ''), $d));
            if (! Decimal::isZero($toStock)) {
                $entry->debit('inventory', $toStock, memo: 'Added to stock cost');
            }
            if (! Decimal::isZero($toCogs)) {
                $entry->debit('cogs', $toCogs, memo: 'For goods already used or sold');
            }
            if ($d->money_account_id !== null) {
                $money = MoneyAccount::query()->findOrFail($d->money_account_id);
                $this->accounts->assertCanPay($money, $total);
                $entry->credit($money, $total);
                $d->forceFill(['paid_amount' => $total])->save();
            } else {
                $entry->credit('accounts_payable', $total, Vendor::query()->withTrashed()->findOrFail($d->vendor_id));
            }
            $journal = $this->journals->post($entry);
            $d->forceFill(['journal_id' => $journal->id])->save();
        }, $overrideReason);
    }

    /**
     * Receipt lines that put stock into a lot, with the spread basis.
     *
     * @return Collection<int, array{line_id: int, lot_id: int, lot_qty: string, basis: string}>
     */
    public function lines(LandedCost $doc): Collection
    {
        return GoodsReceiptLine::query()->whereIn('goods_receipt_id', $doc->receipts()->select('goods_receipts.id'))->whereNotNull('lot_id')->orderBy('id')->get()
            ->map(function (GoodsReceiptLine $line) use ($doc) {
                $qty = Decimal::sum(StockMovement::query()->where('lot_id', $line->lot_id)->where('source_type', (new GoodsReceipt)->getMorphClass())
                    ->where('source_id', $line->goods_receipt_id)->where('source_line_id', $line->id)->pluck('qty_in'), 4);

                return ['line_id' => $line->id, 'lot_id' => (int) $line->lot_id, 'lot_qty' => $qty, 'basis' => $doc->method === 'value' ? (string) $line->value : $qty];
            })
            ->filter(fn (array $l) => Decimal::compare($l['lot_qty'], '0') > 0)
            ->values();
    }
}
