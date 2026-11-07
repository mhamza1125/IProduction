<?php

namespace App\Modules\Opening;

use App\Modules\Opening\Services\Openings;
use App\Modules\Opening\Services\OpeningTypes;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Opening balances and go-live (specification, Module 13).
 */
class OpeningServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Opening';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(OpeningTypes::class);
        $this->app->singleton(Openings::class);
    }

    public function boot(): void
    {
        parent::boot();

        // In the order they are usually loaded.
        foreach ([Types\CashAndBanks::class, Types\OpeningStock::class, Types\ContractorStock::class, Types\CustomerBalances::class, Types\VendorBalances::class,
            Types\ContractorBalances::class, Types\EmployeeBalances::class, Types\EmployeeLoans::class, Types\EmployeeAdvances::class,
            Types\OpenSalesOrders::class, Types\OpenPurchaseOrders::class, Types\RunningPtcs::class] as $type) {
            OpeningTypes::register($type);
        }
    }

    public function permissions(): array
    {
        return [new PermissionSet('opening', 'Opening balances', ['view', 'create', 'delete', 'approve', 'post', 'cancel', 'override'], 'Settings')];
    }

    public function documentTypes(): array
    {
        return [new DocumentType('opening_batch', 'Opening document', 'OPN')];
    }

    public function navigation(): array
    {
        return [new NavItem('Opening Balances', 'opening.index', 'archive', 'opening.view', 'Settings', 985, 'opening.*')];
    }
}
