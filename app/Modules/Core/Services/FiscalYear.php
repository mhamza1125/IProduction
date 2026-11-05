<?php

namespace App\Modules\Core\Services;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Fiscal year helpers. Pakistan's fiscal year runs July to June, so the
 * default start month is 7; change it in company settings if needed.
 */
final class FiscalYear
{
    public function __construct(private readonly Settings $settings) {}

    public function startMonth(): int
    {
        $month = (int) $this->settings->get('fiscal_year.start_month', 7);

        return $month >= 1 && $month <= 12 ? $month : 7;
    }

    public function start(CarbonInterface $date): CarbonImmutable
    {
        $date = CarbonImmutable::instance($date);
        $startMonth = $this->startMonth();
        $year = $date->month >= $startMonth ? $date->year : $date->year - 1;

        return CarbonImmutable::create($year, $startMonth, 1)->startOfDay();
    }

    public function end(CarbonInterface $date): CarbonImmutable
    {
        return $this->start($date)->addYear()->subDay()->endOfDay();
    }

    /**
     * "2026-27" for a July-June year, or "2026" when the year starts in January.
     */
    public function label(CarbonInterface $date): string
    {
        $start = $this->start($date);

        if ($start->month === 1) {
            return (string) $start->year;
        }

        return $start->year.'-'.substr((string) ($start->year + 1), -2);
    }
}
