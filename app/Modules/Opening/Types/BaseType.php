<?php

namespace App\Modules\Opening\Types;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Stage;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Opening\Models\OpeningBatch;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

abstract class BaseType implements OpeningType
{
    /** @var array<string, string> currency => running total */
    protected array $totals = [];

    protected function variant(?string $sku): ?ItemVariant
    {
        return $sku ? ItemVariant::query()->with('item')->where('sku', $sku)->first() : null;
    }

    protected function stage(?string $name): ?Stage
    {
        return $name ? Stage::query()->where('name', $name)->first() : null;
    }

    /** @param  class-string<Model>  $class */
    /**
     * @template T of Model
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    protected function party(string $class, ?string $code): ?Model
    {
        return $code ? $class::query()->where('code', $code)->first() : null;
    }

    protected function positive(?string $value): bool
    {
        return is_numeric($value) && Decimal::compare((string) $value, '0') > 0;
    }

    protected function nonNegative(?string $value): bool
    {
        return is_numeric($value) && ! Decimal::isNegative((string) $value);
    }

    protected function validMonth(?string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}$/', (string) $value) || (bool) strtotime((string) $value);
    }

    protected function month(string $value): Carbon
    {
        return preg_match('/^\d{4}-\d{2}$/', $value) ? Carbon::createFromFormat('Y-m', $value)->startOfMonth() : Carbon::parse($value)->startOfMonth();
    }

    protected function add(string $currency, string $amount): void
    {
        $this->totals[$currency] = Decimal::add($this->totals[$currency] ?? '0', $amount);
    }

    /**
     * Posts one journal per currency: each line against Opening Balance.
     *
     * @param  list<array{account: mixed, amount: string, side: 'debit'|'credit', party?: Model|null, currency: string, memo?: string|null}>  $lines
     */
    protected function journal(OpeningBatch $batch, array $lines): void
    {
        foreach (collect($lines)->filter(fn ($l) => Decimal::compare($l['amount'], '0') > 0)->groupBy('currency') as $currency => $group) {
            $entry = new JournalEntry($batch->batch_date, (string) $currency, "{$batch->number} opening {$batch->typeLabel()}", $batch);
            $debits = '0';
            $credits = '0';
            foreach ($group as $l) {
                if ($l['side'] === 'debit') {
                    $entry->debit($l['account'], $l['amount'], $l['party'] ?? null, $l['memo'] ?? null);
                    $debits = Decimal::add($debits, $l['amount']);
                } else {
                    $entry->credit($l['account'], $l['amount'], $l['party'] ?? null, $l['memo'] ?? null);
                    $credits = Decimal::add($credits, $l['amount']);
                }
            }
            $net = Decimal::sub($debits, $credits);
            if (Decimal::compare($net, '0') > 0) {
                $entry->credit('opening_balance', $net);
            } elseif (Decimal::isNegative($net)) {
                $entry->debit('opening_balance', Decimal::sub('0', $net));
            }
            app(JournalPoster::class)->post($entry);
        }
    }

    /** @return array<string, string> */
    protected function takeTotals(): array
    {
        $t = $this->totals;
        $this->totals = [];

        return $t;
    }
}
