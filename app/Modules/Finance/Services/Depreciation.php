<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\Asset;
use App\Modules\Finance\Models\AssetDepreciation;
use App\Modules\Finance\Models\Journal;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\PeriodLock;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Straight-line depreciation: (cost − salvage value) ÷ useful life, per month,
 * from the depreciation start month, never beyond cost − salvage (the last
 * month takes what is left). Assets that are in use only. One journal per
 * month for all assets: Dr Depreciation / Cr Accumulated Depreciation (PKR),
 * dated the last day of the month. A month is never depreciated twice
 * (unique asset + period), and the period lock applies.
 */
class Depreciation
{
    public function __construct(private readonly JournalPoster $journals, private readonly PeriodLock $periodLock) {}

    public function monthly(Asset $asset): ?string
    {
        if (! $this->depreciable($asset)) {
            return null;
        }

        return Decimal::div(Decimal::sub((string) $asset->value, (string) ($asset->salvage_value ?? '0')), (string) $asset->useful_life_months, 2);
    }

    public function accumulated(Asset $asset): string
    {
        return Decimal::round((string) AssetDepreciation::query()->where('asset_id', $asset->id)->sum('amount'));
    }

    /** Cost less accumulated depreciation, or null when the asset has no cost. */
    public function bookValue(Asset $asset): ?string
    {
        return $asset->value === null ? null : Decimal::sub((string) $asset->value, $this->accumulated($asset));
    }

    /**
     * What the month would post, per asset.
     *
     * @return Collection<int, array{asset: Asset, amount: string}>
     */
    public function due(CarbonInterface $month): Collection
    {
        $period = CarbonImmutable::parse($month)->startOfMonth();

        return Asset::query()->where('status', 'in_use')->whereNotNull('value')->where('useful_life_months', '>', 0)
            ->whereNotNull('depreciation_start')->whereDate('depreciation_start', '<=', $period->endOfMonth()->toDateString())
            ->whereDoesntHave('depreciations', fn ($q) => $q->whereDate('period', $period->toDateString()))
            ->orderBy('code')->get()
            ->map(function (Asset $asset) {
                $remaining = Decimal::sub(Decimal::sub((string) $asset->value, (string) ($asset->salvage_value ?? '0')), $this->accumulated($asset));
                $monthly = (string) $this->monthly($asset);
                $amount = Decimal::compare($remaining, $monthly) < 0 ? $remaining : $monthly;

                return ['asset' => $asset, 'amount' => Decimal::round($amount)];
            })
            ->filter(fn (array $r) => Decimal::compare($r['amount'], '0') > 0)
            ->values();
    }

    /** Posts the month's depreciation; returns null when nothing is due. */
    public function run(CarbonInterface $month): ?Journal
    {
        $period = CarbonImmutable::parse($month)->startOfMonth();
        $date = $period->endOfMonth()->startOfDay();
        $this->periodLock->assertOpen($date);

        return DB::transaction(function () use ($period, $date) {
            // Lock the assets so two people running the same month cannot both post.
            Asset::query()->where('status', 'in_use')->whereNotNull('useful_life_months')->lockForUpdate()->get(['id']);
            $due = $this->due($period);
            if ($due->isEmpty()) {
                return null;
            }

            $total = Decimal::sum($due->pluck('amount')->all());
            $entry = (new JournalEntry($date, 'PKR', 'Depreciation '.$period->format('F Y').' ('.$due->count().' assets)'))
                ->debit('depreciation', $total)
                ->credit('accumulated_depreciation', $total);
            $journal = $this->journals->post($entry);

            foreach ($due as $row) {
                AssetDepreciation::query()->create([
                    'asset_id' => $row['asset']->id, 'period' => $period->toDateString(), 'amount' => $row['amount'],
                    'journal_id' => $journal->id, 'created_by' => auth()->id(),
                ]);
            }

            return $journal;
        });
    }

    private function depreciable(Asset $asset): bool
    {
        return $asset->value !== null && (int) $asset->useful_life_months > 0
            && Decimal::compare((string) $asset->value, (string) ($asset->salvage_value ?? '0')) > 0;
    }

    /** @throws DocumentException when the salvage value is not below cost */
    public static function assertValid(?string $value, ?string $salvage): void
    {
        if ($value !== null && $salvage !== null && Decimal::compare($salvage, $value) >= 0) {
            throw new DocumentException('The salvage value must be less than the cost.');
        }
    }
}
