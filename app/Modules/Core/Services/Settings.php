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
        'base_currency' => 'PKR',
        'fiscal_year.start_month' => 7,
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
