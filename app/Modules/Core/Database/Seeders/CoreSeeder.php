<?php

namespace App\Modules\Core\Database\Seeders;

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/**
 * Safe to run more than once: it only creates what is missing.
 */
class CoreSeeder extends Seeder
{
    public function run(): void
    {
        Artisan::call('permissions:sync');

        $this->seedCurrencies();
        $this->seedAdmin();
    }

    private function seedCurrencies(): void
    {
        $currencies = [
            ['code' => 'PKR', 'name' => 'Pakistani Rupee', 'symbol' => 'Rs', 'base' => true],
            ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'base' => false],
            ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'base' => false],
            ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'base' => false],
            ['code' => 'AED', 'name' => 'UAE Dirham', 'symbol' => 'AED', 'base' => false],
            ['code' => 'CNY', 'name' => 'Chinese Yuan', 'symbol' => '¥', 'base' => false],
        ];

        foreach ($currencies as $data) {
            $currency = Currency::query()->firstOrNew(['code' => $data['code']]);

            if (! $currency->exists) {
                $currency->fill([
                    'name' => $data['name'],
                    'symbol' => $data['symbol'],
                    'decimal_places' => 2,
                    'is_active' => true,
                ]);
            }

            // is_base is not mass assignable on purpose.
            $currency->is_base = $data['base'];
            $currency->save();
        }
    }

    private function seedAdmin(): void
    {
        $email = (string) config('iproduction.admin.email');

        if (User::query()->where('email', $email)->exists()) {
            return;
        }

        $password = (string) (config('iproduction.admin.password') ?: Str::password(16));

        $user = User::query()->create([
            'name' => (string) config('iproduction.admin.name'),
            'email' => $email,
            'password' => $password,
        ]);

        $user->assignRole(SyncPermissions::ADMIN);

        $this->command?->warn("Admin created: {$email} / {$password}");
        $this->command?->warn('Change this password after the first login.');
    }
}
