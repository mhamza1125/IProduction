<?php

namespace App\Modules\Finance\Services;

use App\Support\Finance\Receivable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lists open invoices a customer receipt can be allocated to. Modules
 * register a finder for their receivable documents (keyed).
 */
class Receivables
{
    /** @var array<string, Closure(Model): Collection<int, Model&Receivable>> */
    private static array $finders = [];

    /** @param  Closure(Model): Collection<int, Model&Receivable>  $finder */
    public static function register(string $key, Closure $finder): void
    {
        self::$finders[$key] = $finder;
    }

    /** @return Collection<int, Model&Receivable> */
    public function openFor(Model $customer): Collection
    {
        $all = collect();
        foreach (self::$finders as $finder) {
            $all = $all->merge($finder($customer));
        }

        return $all->filter(fn (Receivable $r) => (float) $r->receivableBalance() > 0)->values();
    }
}
