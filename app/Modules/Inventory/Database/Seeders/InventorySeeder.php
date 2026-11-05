<?php

namespace App\Modules\Inventory\Database\Seeders;

use App\Modules\Inventory\Models\Location;
use App\Modules\People\Models\Contractor;
use Illuminate\Database\Seeder;

/**
 * Main Store, Production Floor, and one location per contractor. Safe to re-run.
 */
class InventorySeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            'main_store' => ['MAIN', 'Main Store', Location::STORE],
            'production_floor' => ['FLOOR', 'Production Floor', Location::PRODUCTION],
        ] as $key => [$code, $name, $type]) {
            if (! Location::query()->where('system_key', $key)->exists()) {
                $location = new Location(['code' => $code, 'name' => $name, 'type' => $type]);
                $location->system_key = $key;
                $location->save();
            }
        }

        Contractor::query()->withTrashed()->each(fn (Contractor $c) => Location::forContractor($c));
    }
}
