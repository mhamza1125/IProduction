<?php

namespace App\Modules\Finance\Services;

use App\Support\Finance\Payable;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Lists open bills / jobs / payrolls a payee can be paid against. Modules
 * register a finder for their payable documents (keyed).
 */
class Payables
{
    /** @var array<string, Closure(Model): Collection<int, Model&Payable>> */
    private static array $finders = [];

    /**
     * @param  Closure(Model): Collection<int, Model&Payable>  $finder
     */
    public static function register(string $key, Closure $finder): void
    {
        self::$finders[$key] = $finder;
    }

    /**
     * @return Collection<int, Model&Payable>
     */
    public function openFor(Model $payee): Collection
    {
        $all = collect();

        foreach (self::$finders as $finder) {
            $all = $all->merge($finder($payee));
        }

        return $all->filter(fn (Payable $p) => (float) $p->payableBalance() > 0)->values();
    }
}
