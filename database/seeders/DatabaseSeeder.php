<?php

namespace Database\Seeders;

use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Finance\Database\Seeders\ChartOfAccountsSeeder;
use App\Modules\Inventory\Database\Seeders\InventorySeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seeds the data every deployment needs. Each module adds its own seeder here.
     * Safe to run again on an existing database.
     */
    public function run(): void
    {
        $this->call([
            CoreSeeder::class,
            ChartOfAccountsSeeder::class,
            InventorySeeder::class,
        ]);
    }
}
