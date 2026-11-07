<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Cache;

/**
 * Company-wide settings stored in the `settings` table and cached.
 *
 *   app(Settings::class)->get('company.name');
 *   app(Settings::class)->set('company.name', 'Acme Industries');
 *
 * Use this for anything that differs between deployments (company name,
 * tax numbers, fiscal year) instead of hard-coding it.
 */
class Settings
{
    private const CACHE_KEY = 'core.settings';

    /** Values used when a key has never been saved. */
    public const DEFAULTS = [
        'company.name' => 'My Company',
        'company.address' => null,
        'company.phone' => null,
        'company.email' => null,
        'company.ntn' => null,
        'company.strn' => null,
        'company.logo_path' => null,
        'company.cnic' => null,
        'company.invoice_terms' => null,
        'company.print_header' => null,
        'company.print_footer' => null,
        'base_currency' => 'PKR',
        'fiscal_year.start_month' => 7,
        'period_lock.date' => null,
        'period_lock.reason' => null,
        // Over-receipt tolerance in percent (Purchasing, contractor jobs, PTC over-output).
        'tolerance.over_receipt_percent' => '0',
        // Payroll (Module 11).
        'payroll.salary_day_basis' => 30,
        'payroll.daily_hours' => '8',
        'payroll.deduct_unpaid_days' => true,
        'payroll.deduct_late_hours' => true,
        // Overtime = hours × hourly rate × multiplier, salaried employees only.
        'payroll.overtime_multiplier' => '1',
    ];

    /** @var array<string, mixed>|null */
    private ?array $loaded = null;

    public function get(string $key, mixed $default = null): mixed
    {
        $all = $this->all();

        return array_key_exists($key, $all) ? $all[$key] : ($default ?? self::DEFAULTS[$key] ?? null);
    }

    public function set(string $key, mixed $value): void
    {
        $this->setMany([$key => $value]);
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::query()->updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->loaded ??= array_replace(
            self::DEFAULTS,
            Cache::rememberForever(self::CACHE_KEY, fn () => Setting::query()->pluck('value', 'key')->all()),
        );
    }

    public function flush(): void
    {
        $this->loaded = null;
        Cache::forget(self::CACHE_KEY);
    }
}
