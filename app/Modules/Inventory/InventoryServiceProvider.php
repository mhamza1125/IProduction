<?php

namespace App\Modules\Inventory;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Inventory\Console\RebuildBalances;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\Inventory\Services\CountSheet;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\People\Models\Contractor;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Inventory (Module 4): locations, lots, the stock movement ledger,
 * balances, reservations and stock adjustments.
 */
class InventoryServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Inventory';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(StockLedger::class);
        $this->app->singleton(CountSheet::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([RebuildBalances::class]);
        }

        // One location per contractor, created automatically.
        Contractor::created(fn (Contractor $contractor) => Location::forContractor($contractor));
        Contractor::updated(function (Contractor $contractor) {
            Location::query()->where('contractor_id', $contractor->id)->update(['name' => $contractor->name, 'is_active' => $contractor->is_active]);
        });

        // Anything with stock history can only be deactivated.
        ItemVariant::whenUsed(fn (ItemVariant $v) => StockMovement::query()->where('item_variant_id', $v->id)->exists());
        Item::whenUsed(fn (Item $i) => StockMovement::query()->whereIn('item_variant_id', $i->variants()->withTrashed()->select('id'))->exists());
        Stage::whenUsed(fn (Stage $s) => StockMovement::query()->where('stage_id', $s->id)->exists());
        Contractor::whenUsed(fn (Contractor $c) => StockMovement::query()->whereIn('location_id', Location::query()->where('contractor_id', $c->id)->select('id'))->exists());
        Lookup::whenUsed(fn (Lookup $l) => $l->type === 'adjustment_reason' && StockAdjustment::query()->where('reason_id', $l->id)->exists());
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('stock', 'Stock', ['view', 'export'], 'Inventory'),
            new PermissionSet('stock_movements', 'Stock movements', ['view', 'export'], 'Inventory'),
            new PermissionSet('lots', 'Lots', ['view', 'export'], 'Inventory'),
            new PermissionSet('reservations', 'Reservations', ['view', 'create', 'cancel', 'override', 'export'], 'Inventory'),
            new PermissionSet('stock_adjustments', 'Stock adjustments', PermissionSet::DOCUMENT, 'Inventory'),
            new PermissionSet('locations', 'Locations', ['view', 'create', 'edit'], 'Inventory'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('stock_adjustment', 'Stock Adjustment', 'ADJ'),
            new DocumentType('lot', 'Lot number', 'LOT', approvable: false),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Stock', 'inventory.stock', 'cube', 'stock.view', 'Inventory', 400),
            new NavItem('Stock Movements', 'inventory.movements', 'arrows', 'stock_movements.view', 'Inventory', 410),
            new NavItem('Lots', 'inventory.lots.index', 'tag', 'lots.view', 'Inventory', 420, 'inventory.lots.*'),
            new NavItem('Reservations', 'inventory.reservations', 'archive', 'reservations.view', 'Inventory', 430),
            new NavItem('Stock Adjustments', 'inventory.adjustments.index', 'scale', 'stock_adjustments.view', 'Inventory', 440, 'inventory.adjustments.*'),
            new NavItem('Locations', 'inventory.locations', 'building', 'locations.view', 'Inventory', 450),
        ];
    }
}
