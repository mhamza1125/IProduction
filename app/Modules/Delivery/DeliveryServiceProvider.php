<?php

namespace App\Modules\Delivery;

use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\CreditNotes;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\Delivery\Services\DeliveryReturns;
use App\Modules\Delivery\Services\Invoices;
use App\Modules\Delivery\Services\Packing;
use App\Modules\Finance\Services\Receivables;
use App\Modules\People\Models\Customer;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Delivery and billing (specification, Module 9): deliveries, packing lists,
 * invoices, delivery returns and credit notes.
 */
class DeliveryServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Delivery';
    }

    public function register(): void
    {
        parent::register();

        foreach ([Deliveries::class, Packing::class, Invoices::class, CreditNotes::class, DeliveryReturns::class] as $service) {
            $this->app->singleton($service);
        }
    }

    public function boot(): void
    {
        parent::boot();

        Customer::whenUsed('delivery.customer', fn (Customer $c) => Invoice::query()->where('customer_id', $c->id)->exists()
            || Delivery::query()->where('customer_id', $c->id)->exists());

        // Customer receipts can be allocated to posted invoices.
        Receivables::register('delivery.invoices', fn ($customer) => $customer instanceof Customer
            ? Invoice::query()->where('customer_id', $customer->id)->whereNotNull('posted_at')->orderBy('invoice_date')->get()
            : collect());
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('deliveries', 'Deliveries', [...PermissionSet::DOCUMENT, 'override'], 'Sales'),
            new PermissionSet('packing_lists', 'Packing lists', ['view', 'edit', 'print'], 'Sales'),
            new PermissionSet('invoices', 'Invoices', [...PermissionSet::DOCUMENT, 'override'], 'Sales'),
            new PermissionSet('delivery_returns', 'Delivery returns', PermissionSet::DOCUMENT, 'Sales'),
            new PermissionSet('credit_notes', 'Credit notes', PermissionSet::DOCUMENT, 'Sales'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('delivery', 'Delivery', 'DN', approvable: false),
            new DocumentType('invoice', 'Invoice', 'INV'),
            new DocumentType('delivery_return', 'Delivery Return', 'DR'),
            new DocumentType('credit_note', 'Credit Note', 'CN'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Deliveries', 'delivery.deliveries.index', 'truck', 'deliveries.view', 'Sales', 130, 'delivery.deliveries.*'),
            new NavItem('Invoices', 'delivery.invoices.index', 'document', 'invoices.view', 'Sales', 140, 'delivery.invoices.*'),
            new NavItem('Returns', 'delivery.returns.index', 'arrow-uturn-left', 'delivery_returns.view', 'Sales', 150, 'delivery.returns.*'),
            new NavItem('Credit Notes', 'delivery.credit-notes.index', 'banknotes', 'credit_notes.view', 'Sales', 160, 'delivery.credit-notes.*'),
        ];
    }
}
