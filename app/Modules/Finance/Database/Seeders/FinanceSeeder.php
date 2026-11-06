<?php

namespace App\Modules\Finance\Database\Seeders;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\MoneyAccounts;
use Illuminate\Database\Seeder;

/**
 * The permanent Cash Book. Safe to re-run.
 */
class FinanceSeeder extends Seeder
{
    public function run(): void
    {
        if (MoneyAccount::query()->where('system_key', 'cash_book')->exists()) {
            return;
        }

        $cash = app(MoneyAccounts::class)->create(['name' => 'Cash Book', 'type' => MoneyAccount::CASH, 'currency_code' => 'PKR']);
        $cash->forceFill(['system_key' => 'cash_book'])->save();
    }
}
