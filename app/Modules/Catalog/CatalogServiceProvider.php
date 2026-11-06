<?php

namespace App\Modules\Catalog;

use App\Modules\Catalog\Models\BomLine;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\RouteStage;
use App\Modules\Catalog\Services\BomExplosion;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Catalog\Services\VariantGenerator;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Catalog (Module 3): items, attributes, variants, routes and BOMs.
 */
class CatalogServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Catalog';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(VariantGenerator::class);
        $this->app->singleton(BomService::class);
        $this->app->singleton(BomExplosion::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Master records used by the catalog can only be deactivated.
        Unit::whenUsed('catalog.unit', fn (Unit $u) => Item::withTrashed()->where(fn ($q) => $q->where('stock_unit_id', $u->id)->orWhere('purchase_unit_id', $u->id)->orWhere('sales_unit_id', $u->id))->exists()
            || BomLine::query()->where('unit_id', $u->id)->exists());
        Stage::whenUsed('catalog.stage', fn (Stage $s) => RouteStage::query()->where('stage_id', $s->id)->exists() || BomLine::query()->where('stage_id', $s->id)->exists());
        Lookup::whenUsed('catalog.lookup', fn (Lookup $l) => $l->type === 'item_category' && Item::withTrashed()->where('category_id', $l->id)->exists());
        Item::whenUsed('catalog.item', fn (Item $i) => BomLine::query()->where('component_item_id', $i->id)->exists() || $i->boms()->exists());
        ItemVariant::whenUsed('catalog.itemvariant', fn (ItemVariant $v) => BomLine::query()->where('component_variant_id', $v->id)->exists());
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('items', 'Items and variants', [...PermissionSet::MASTER, 'print'], 'Catalog'),
            new PermissionSet('attributes', 'Attributes', ['view', 'create', 'edit', 'delete'], 'Catalog'),
            new PermissionSet('routes', 'Routes', PermissionSet::MASTER, 'Catalog'),
            new PermissionSet('boms', 'BOMs', ['view', 'create', 'edit', 'delete', 'approve', 'print', 'export'], 'Catalog'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('item', 'Item code', 'ITM', approvable: false, yearFormat: 'none'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Items', 'catalog.items.index', 'cube', 'items.view', 'Catalog', 500, 'catalog.items.*'),
            new NavItem('Variants', 'catalog.variants.index', 'tag', 'items.view', 'Catalog', 510),
            new NavItem('Attributes', 'catalog.attributes.index', 'list', 'attributes.view', 'Catalog', 520),
            new NavItem('Routes', 'catalog.routes.index', 'arrows', 'routes.view', 'Catalog', 530, 'catalog.routes.*'),
            new NavItem('BOMs', 'catalog.boms.index', 'clipboard', 'boms.view', 'Catalog', 540, 'catalog.boms.*'),
        ];
    }
}
