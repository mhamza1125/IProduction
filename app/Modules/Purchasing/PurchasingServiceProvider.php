<?php

namespace App\Modules\Purchasing;

use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Finance\Services\Payables;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseOrderLine;
use App\Modules\Purchasing\Models\VendorBill;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Modules\Purchasing\Services\Tolerance;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;
use Illuminate\Database\Eloquent\Model;

/**
 * Purchasing (Module 6): purchase orders, goods receipts, vendor bills and
 * purchase returns. Vendor payments are in Finance.
 */
class PurchasingServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Purchasing';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(PurchaseOrders::class);
        $this->app->singleton(Tolerance::class);
    }

    public function boot(): void
    {
        parent::boot();

        Vendor::whenUsed('purchasing.orders', fn (Vendor $v) => PurchaseOrder::query()->where('vendor_id', $v->id)->exists());
        ItemVariant::whenUsed('purchasing.lines', fn (ItemVariant $v) => PurchaseOrderLine::query()->where('item_variant_id', $v->id)->exists());
        Item::whenUsed('purchasing.lines', fn (Item $i) => PurchaseOrderLine::query()->whereIn('item_variant_id', $i->variants()->withTrashed()->select('id'))->exists());

        Payables::register('purchasing.bills', fn (Model $payee) => $payee instanceof Vendor
            ? VendorBill::query()->where('vendor_id', $payee->id)->whereNotNull('posted_at')->whereColumn('paid_amount', '<', 'total')->orderBy('bill_date')->get()
            : collect());
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('purchase_orders', 'Purchase orders', PermissionSet::DOCUMENT, 'Purchasing'),
            new PermissionSet('goods_receipts', 'Goods receipts', [...PermissionSet::DOCUMENT, 'override'], 'Purchasing'),
            new PermissionSet('vendor_bills', 'Vendor bills', [...PermissionSet::DOCUMENT, 'override'], 'Purchasing'),
            new PermissionSet('purchase_returns', 'Purchase returns', PermissionSet::DOCUMENT, 'Purchasing'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('purchase_order', 'Purchase Order', 'PO'),
            new DocumentType('goods_receipt', 'Goods Receipt', 'GRN'),
            new DocumentType('vendor_bill', 'Vendor Bill', 'BILL'),
            new DocumentType('purchase_return', 'Purchase Return', 'PR'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Purchase Orders', 'purchasing.orders.index', 'clipboard', 'purchase_orders.view', 'Purchasing', 210, 'purchasing.orders.*'),
            new NavItem('Goods Receipts', 'purchasing.receipts.index', 'inbox', 'goods_receipts.view', 'Purchasing', 220, 'purchasing.receipts.*'),
            new NavItem('Vendor Bills', 'purchasing.bills.index', 'document', 'vendor_bills.view', 'Purchasing', 230, 'purchasing.bills.*'),
            new NavItem('Purchase Returns', 'purchasing.returns.index', 'arrow-uturn-left', 'purchase_returns.view', 'Purchasing', 240, 'purchasing.returns.*'),
        ];
    }
}
