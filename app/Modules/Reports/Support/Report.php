<?php

namespace App\Modules\Reports\Support;

use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Support\Collection;

/**
 * A report reads posted data only and returns rows for the screen, print and
 * export. Subclasses declare filters and columns and build rows.
 */
abstract class Report
{
    abstract public function key(): string;

    abstract public function title(): string;

    /** Area on the reports page: Daily, Stock, Sales, ... */
    abstract public function group(): string;

    public function description(): string
    {
        return '';
    }

    /**
     * Permissions needed besides reports.view (e.g. cost.view).
     *
     * @return list<string>
     */
    public function permissions(): array
    {
        return [];
    }

    /** @return list<Filter> */
    public function filters(): array
    {
        return [];
    }

    /** @return list<Column> */
    abstract public function columns(): array;

    /**
     * Builds the rows: any iterable of arrays keyed by column key.
     *
     * @param  array<string, string|null>  $filters
     * @return iterable<array-key, array<array-key, mixed>>
     */
    abstract protected function build(array $filters): iterable;

    /**
     * @param  array<string, string|null>  $filters
     * @return Collection<int, array<array-key, mixed>>
     */
    final public function rows(array $filters): Collection
    {
        return collect($this->build($filters))->values();
    }

    /** A note printed under the report (e.g. "direct cost only"). */
    public function note(): ?string
    {
        return null;
    }

    public function landscape(): bool
    {
        return count($this->columns()) > 7;
    }

    /**
     * Totals of the columns marked total, per currency when the column has one.
     *
     * @param  Collection<int, array<array-key, mixed>>  $rows
     * @return array<string, array<string, string>> column key => currency (or '') => total
     */
    public function totals(Collection $rows): array
    {
        $out = [];
        foreach ($this->columns() as $c) {
            if (! $c->total) {
                continue;
            }
            foreach ($rows->groupBy(fn ($r) => $c->currencyKey ? (string) ($r[$c->currencyKey] ?? '') : '') as $currency => $group) {
                $out[$c->key][$currency] = Decimal::sum($group->pluck($c->key)->filter(fn ($v) => $v !== null && $v !== '')->map(fn ($v) => (string) $v), $c->type === 'qty' ? 4 : 2);
            }
        }

        return $out;
    }

    /**
     * Default filter values.
     *
     * @return array<string, string|null>
     */
    public function defaults(): array
    {
        $out = [];
        foreach ($this->filters() as $f) {
            $out[$f->key] = $f->default;
        }

        return $out;
    }

    /** @param  array<string, string|null>  $filters */
    protected function from(array $filters): ?Carbon
    {
        return ! empty($filters['from']) ? Carbon::parse($filters['from'])->startOfDay() : null;
    }

    /** @param  array<string, string|null>  $filters */
    protected function to(array $filters): ?Carbon
    {
        return ! empty($filters['to']) ? Carbon::parse($filters['to'])->endOfDay() : null;
    }

    /**
     * Standard date range filters, this month by default.
     *
     * @return list<Filter>
     */
    protected function dateRange(?string $from = null, ?string $to = null): array
    {
        return [
            new Filter('from', 'From', 'date', default: $from ?? now()->startOfMonth()->toDateString()),
            new Filter('to', 'To', 'date', default: $to ?? now()->toDateString()),
        ];
    }
}
