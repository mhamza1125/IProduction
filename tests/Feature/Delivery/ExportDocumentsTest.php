<?php

use App\Models\User;
use App\Modules\Catalog\Livewire\Items\Form as ItemForm;
use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Delivery\Livewire\Invoices\Show as InvoiceScreen;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\Invoices;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Documents\DocumentException;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DemoDataSeeder;
use Livewire\Livewire;

/** Export documentation on the demo company: commercial invoice, proforma and shipping details. */
beforeEach(function () {
    $this->seed(DatabaseSeeder::class);
    $this->seed(DemoDataSeeder::class);
    $this->actingAs(User::role(SyncPermissions::ADMIN)->firstOrFail());
});

it('prints a commercial invoice with HS codes, origin, shipping details and the statement on origin', function () {
    $invoice = Invoice::query()->where('number', 'INV-26-0001')->firstOrFail();

    $this->get(route('delivery.invoices.print', $invoice))->assertOk()
        ->assertSee('Commercial Invoice')->assertSee('9506.91.00')->assertSee('9506.62.00')->assertSee('Pakistan')
        ->assertSee('FOB Karachi')->assertSee('MEDUKH000427')->assertSee('MEZN-EXP-2026-00118')->assertSee('KEXP-HC-0099812')
        ->assertSee('REX No PKREXPK0000000000')->assertSee('of Pakistan preferential origin')->assertSee('IBAN: PK00 MEZN');

    expect($this->get(route('delivery.invoices.print', ['invoice' => $invoice, 'pdf' => 1]))->headers->get('content-type'))->toContain('pdf');
});

it('copies the customer usual Incoterm onto new invoices', function () {
    // INV-26-0002 (Rheinland) was saved after the customer had CIF Hamburg as usual terms.
    expect(Invoice::query()->where('number', 'INV-26-0002')->firstOrFail())
        ->incoterm->toBe('CIF')->incoterm_place->toBe('Hamburg')->port_of_discharge->toBe('Hamburg');
});

it('completes shipping details after posting, but not on cancelled invoices or with an unknown Incoterm', function () {
    $invoice = Invoice::query()->where('number', 'INV-26-0002')->firstOrFail();
    expect($invoice->isPosted())->toBeTrue();

    Livewire::test(InvoiceScreen::class, ['invoice' => $invoice])->call('editShipping')
        ->set('shipping.bl_awb_no', 'HLCUHAM260001')->set('shipping.gd_number', 'KEXP-HC-0100230')->set('shipping.incoterm', 'XYZ')
        ->call('saveShipping')->assertHasErrors(['shipping.incoterm'])
        ->set('shipping.incoterm', 'CIF')->call('saveShipping')->assertHasNoErrors();

    expect($invoice->fresh())->bl_awb_no->toBe('HLCUHAM260001')->gd_number->toBe('KEXP-HC-0100230')->total->toBe('4184.00');

    $invoice->forceFill(['status' => 'cancelled'])->saveQuietly();
    expect(fn () => app(Invoices::class)->updateShipping($invoice->fresh(), ['bl_awb_no' => 'X']))->toThrow(DocumentException::class);

    $this->actingAs(userWith(['invoices.view']));
    Livewire::test(InvoiceScreen::class, ['invoice' => $invoice->fresh()])->call('editShipping')->assertForbidden();
});

it('prints a proforma invoice from a draft or approved order, not from a cancelled one', function () {
    $draft = SalesOrder::query()->where('status', 'draft')->firstOrFail();
    $cancelled = SalesOrder::query()->where('status', 'cancelled')->firstOrFail();

    $this->get(route('sales.orders.proforma', $draft))->assertOk()
        ->assertSee('Proforma Invoice')->assertSee('Valid until')->assertSee('CFR Jebel Ali')->assertSee('9506.62.00')->assertSee('SWIFT: MEZNPKKA')
        ->assertDontSee('preferential origin');
    $this->get(route('sales.orders.proforma', $cancelled))->assertForbidden();
    $this->actingAs(userWith(['sales_orders.view']));
    $this->get(route('sales.orders.proforma', $draft))->assertForbidden();
});

it('validates HS codes on items', function () {
    $item = Item::query()->where('code', 'FG-BOX')->firstOrFail();

    Livewire::test(ItemForm::class, ['item' => $item])->set('hs_code', 'ABC-1')->call('save')->assertHasErrors(['hs_code'])
        ->set('hs_code', '9506.91.00')->set('origin_country', 'Pakistan')->call('save')->assertHasNoErrors();
    expect($item->fresh())->hs_code->toBe('9506.91.00')->origin_country->toBe('Pakistan');
});
