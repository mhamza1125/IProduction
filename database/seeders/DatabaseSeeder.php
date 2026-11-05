<?php

namespace Database\Seeders;

use App\Modules\Core\Database\Seeders\CoreSeeder;
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
        ]);
    }
}
