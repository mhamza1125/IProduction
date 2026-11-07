<?php

namespace App\Modules\Reports\Services;

use App\Models\User;
use App\Modules\Reports\Support\Report;
use Illuminate\Support\Collection;

/**
 * Registry of reports (keyed by Report::key()).
 */
class Reports
{
    /** @var array<string, class-string<Report>> */
    private static array $reports = [];

    /** @param  class-string<Report>  $class */
    public static function register(string $class): void
    {
        self::$reports[app($class)->key()] = $class;
    }

    public function find(string $key): ?Report
    {
        return isset(self::$reports[$key]) ? app(self::$reports[$key]) : null;
    }

    public function allowed(Report $report, ?User $user): bool
    {
        if (! $user?->can('reports.view')) {
            return false;
        }
        foreach ($report->permissions() as $p) {
            if (! $user->can($p)) {
                return false;
            }
        }

        return true;
    }

    /** @return Collection<array-key, Collection<int, Report>> group => reports the user may open */
    public function forUser(?User $user): Collection
    {
        $allowed = [];
        foreach (self::$reports as $class) {
            $report = app($class);
            if ($report instanceof Report && $this->allowed($report, $user)) {
                $allowed[] = $report;
            }
        }
        usort($allowed, fn (Report $a, Report $b) => strcmp($a->title(), $b->title()));

        return collect($allowed)->groupBy(fn (Report $r) => $r->group());
    }
}
