<?php

namespace App\Modules\Sales;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\People\Models\Customer;
use App\Modules\Purchasing\Actions\PostGoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Models\SalesOrderLine;
use App\Modules\Sales\Services\Planning;
use App\Modules\Sales\Services\ReceiptReservations;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Sales (Module 5, later Module 9): sales orders and planning.
 */
class SalesServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Sales';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(SalesOrders::class);
        $this->app->singleton(Planning::class);
    }

    public function boot(): void
    {
        parent::boot();

        Customer::whenUsed('sales.orders', fn (Customer $c) => SalesOrder::query()->where('customer_id', $c->id)->exists());
        ItemVariant::whenUsed('sales.lines', fn (ItemVariant $v) => SalesOrderLine::query()->where('item_variant_id', $v->id)->exists());
        Item::whenUsed('sales.lines', fn (Item $i) => SalesOrderLine::query()->whereIn('item_variant_id', $i->variants()->withTrashed()->select('id'))->exists());

        // Finished goods bought for an order are reserved to it when they arrive.
        PostGoodsReceipt::after('sales.reserve', fn (GoodsReceipt $grn) => app(ReceiptReservations::class)($grn));
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('sales_orders', 'Sales orders', [...PermissionSet::DOCUMENT, 'override'], 'Sales'),
            new PermissionSet('planning', 'Planning', ['view', 'create'], 'Sales'),
        ];
    }

    public function documentTypes(): array
    {
        return [new DocumentType('sales_order', 'Sales Order', 'SO')];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Sales Orders', 'sales.orders.index', 'document', 'sales_orders.view', 'Sales', 110, 'sales.orders.*'),
            new NavItem('Planning', 'sales.planning', 'calendar', 'planning.view', 'Sales', 120),
        ];
    }
}
