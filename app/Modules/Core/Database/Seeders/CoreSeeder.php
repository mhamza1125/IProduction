<?php

namespace App\Modules\Core\Database\Seeders;

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Models\UnitConversion;
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
        $this->seedUnits();
        $this->seedStages();
        $this->seedLookups();
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

    private function seedUnits(): void
    {
        foreach (Unit::DEFAULTS as $name => [$symbol, $decimals]) {
            Unit::query()->withTrashed()->firstOrCreate(['symbol' => $symbol], ['name' => $name, 'allows_decimal' => $decimals]);
        }

        $piece = Unit::query()->where('symbol', 'pcs')->value('id');
        $dozen = Unit::query()->where('symbol', 'dozen')->value('id');
        $kg = Unit::query()->where('symbol', 'kg')->value('id');
        $gram = Unit::query()->where('symbol', 'g')->value('id');

        foreach ([[$dozen, $piece, '12'], [$kg, $gram, '1000']] as [$from, $to, $factor]) {
            UnitConversion::query()->firstOrCreate(['from_unit_id' => $from, 'to_unit_id' => $to, 'item_id' => null], ['factor' => $factor]);
        }
    }

    /**
     * Example stages from the specification. Rename or add more in Settings > Stages.
     */
    private function seedStages(): void
    {
        if (Stage::query()->withTrashed()->exists()) {
            return;
        }

        foreach (['Stitching', 'Stamping', 'Leather Attach', 'Logo Embossing', 'Magzi'] as $i => $name) {
            Stage::query()->create(['name' => $name, 'sort_order' => ($i + 1) * 10]);
        }
    }

    /**
     * Starter pick-lists. Users edit them in Settings > Lookups.
     */
    private function seedLookups(): void
    {
        $defaults = [
            'payment_method' => ['Cash', 'Bank Transfer', 'Cheque'],
            'adjustment_reason' => ['Physical count', 'Damaged', 'Found', 'Expired'],
            'rejection_reason' => ['Stitching defect', 'Wrong size', 'Material defect', 'Stain or mark'],
            'wastage_reason' => ['Cutting waste', 'Machine fault', 'Handling'],
            'deduction' => ['Fine', 'Damage charge'],
            'allowance' => ['Overtime', 'Bonus', 'Travel'],
        ];

        foreach ($defaults as $type => $names) {
            if (Lookup::query()->withTrashed()->where('type', $type)->exists()) {
                continue;
            }

            foreach ($names as $i => $name) {
                Lookup::query()->create(['type' => $type, 'name' => $name, 'sort_order' => $i * 10]);
            }
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
