<?php

use App\Modules\Catalog\CatalogServiceProvider;
use App\Modules\Core\CoreServiceProvider;
use App\Modules\Finance\FinanceServiceProvider;
use App\Modules\Inventory\InventoryServiceProvider;
use App\Modules\People\PeopleServiceProvider;
use App\Providers\AppServiceProvider;
use App\Providers\FortifyServiceProvider;
use App\Providers\HorizonServiceProvider;

return [
    AppServiceProvider::class,
    FortifyServiceProvider::class,
    HorizonServiceProvider::class,

    // Business modules (app/Modules). Add each new module's provider here.
    CoreServiceProvider::class,
    PeopleServiceProvider::class,
    CatalogServiceProvider::class,
    InventoryServiceProvider::class,
    FinanceServiceProvider::class,
];
