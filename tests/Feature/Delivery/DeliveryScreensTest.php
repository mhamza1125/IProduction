<?php

use App\Modules\Catalog\Models\Item;
use App\Modules\Delivery\Livewire\CreditNotes;
use App\Modules\Delivery\Livewire\Deliveries;
use App\Modules\Delivery\Livewire\Invoices;
use App\Modules\Delivery\Livewire\Returns;
use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\CreditNotes as CreditNoteService;
use App\Modules\Delivery\Services\DeliveryReturns;
use App\Modules\Delivery\Services\Invoices as InvoiceService;
use App\Modules\Delivery\Services\Packing;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockStatus;
use App\Modules\Inventory\Services\StockBucket;
use App\Modules\Inventory\Services\StockLedger;
use App\Modules\Inventory\Services\StockSource;
use App\Modules\People\Models\Customer;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\TestDocument;

beforeEach(function () {
    seedBase();
    $this->actingAs(admin());
    $this->customer = Customer::factory()->create(['currency_code' => 'USD']);
    $this->variant = Item::factory()->finished()->create(['code' => 'GLV'])->variants()->first();
    DB::transaction(function () {
        $doc = TestDocument::create(['number' => 'TD-1', 'document_date' => '2026-11-01']);
        $lot = app(StockLedger::class)->createLot($this->variant->id, 'purchased', '2', Carbon::parse('2026-11-01'));
        app(StockLedger::class)->receive(new StockBucket($this->variant->id, null, StockStatus::AVAILABLE, Location::mainStore()->id, $lot->id), '100', new StockSource($doc, Carbon::parse('2026-11-01')));
    });
    $order = app(SalesOrders::class)->save(null, ['customer_id' => $this->customer->id, 'order_date' => '2026-11-01'], [[
        'item_variant_id' => $this->variant->id, 'description' => '', 'quantity' => '100', 'unit_price' => '5', 'tax_rate' => '', 'due_date' => null]]);
    $this->line = app(DocumentWorkflow::class)->approve($order)->lines()->first();
});

it('runs a delivery from the screens: lines, allocate, carton, pack, dispatch, invoice', function () {
    Livewire::test(Deliveries\Form::class)
        ->set('customer_id', $this->customer->id)->assertSee($this->line->order->number)
        ->call('fillRemaining')->assertSet("quantities.{$this->line->id}", '100.0000')
        ->call('save')->assertHasNoErrors()->assertRedirect();
    $delivery = Delivery::query()->sole();
    $dlId = $delivery->lines()->value('id');

    Livewire::test(Deliveries\Show::class, ['delivery' => $delivery])
        ->call('allocate')
        ->set("contents.$dlId", '25')->set('repeat', 4)->set('carton.gross_weight', '10')->call('addCarton')
        ->call('completePacking')
        ->call('dispatchDelivery')
        ->assertSee('Packing list');
    expect($delivery->fresh()->status)->toBe('dispatched');

    Livewire::test(Invoices\Form::class, ['customer' => $this->customer->id])
        ->set('customer_id', $this->customer->id)->set("lines.$dlId.selected", true)->set("lines.$dlId.tax_rate", '')
        ->call('save')->assertHasNoErrors()->assertRedirect();
    $invoice = Invoice::query()->sole();
    expect($invoice->total)->toBe('500.00')->and($invoice->tax_total)->toBe('0.00');

    Livewire::test(Invoices\Show::class, ['invoice' => $invoice])->call('approveDocument')->call('postDocumentAction');
    expect($invoice->fresh()->isPosted())->toBeTrue();
});

it('renders every delivery and billing screen and print', function () {
    $d = app(App\Modules\Delivery\Services\Deliveries::class)->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [['sales_order_line_id' => $this->line->id, 'quantity' => '10']]);
    $d = app(App\Modules\Delivery\Services\Deliveries::class)->allocate($d);
    app(Packing::class)->addCarton($d, [], [$d->lines()->value('id') => '10']);
    app(Packing::class)->complete($d);
    $d = app(App\Modules\Delivery\Services\Deliveries::class)->dispatch($d->fresh());
    $invoice = app(InvoiceService::class)->post(app(DocumentWorkflow::class)->approve(app(InvoiceService::class)->save(null, ['customer_id' => $this->customer->id, 'invoice_date' => '2026-11-11'], [['delivery_line_id' => $d->lines()->value('id')]])));
    $return = app(DeliveryReturns::class)->post(app(DocumentWorkflow::class)->approve(app(DeliveryReturns::class)->save(null, ['delivery_line_id' => $d->lines()->value('id'), 'quantity' => '1', 'reason' => 'Torn', 'return_date' => '2026-11-12'])));
    $note = app(CreditNoteService::class)->post(app(DocumentWorkflow::class)->approve(app(CreditNoteService::class)->save(null, ['customer_id' => $this->customer->id, 'invoice_id' => $invoice->id, 'delivery_return_id' => $return->id, 'credit_date' => '2026-11-12', 'reason' => 'Return'], [['description' => 'Return', 'amount' => '5']])));

    foreach ([
        route('delivery.deliveries.index'), route('delivery.deliveries.create'), route('delivery.deliveries.show', $d), route('delivery.deliveries.print', $d), route('delivery.deliveries.packing', $d),
        route('delivery.invoices.index'), route('delivery.invoices.create'), route('delivery.invoices.show', $invoice), route('delivery.invoices.print', $invoice),
        route('delivery.returns.index'), route('delivery.returns.create'), route('delivery.returns.show', $return), route('delivery.returns.print', $return),
        route('delivery.credit-notes.index'), route('delivery.credit-notes.create', ['invoice' => $invoice->id]), route('delivery.credit-notes.create', ['return' => $return->id]),
        route('delivery.credit-notes.show', $note), route('delivery.credit-notes.print', $note),
        route('sales.orders.show', $this->line->sales_order_id),
    ] as $url) {
        $this->get($url)->assertOk();
    }
    $this->get(route('delivery.invoices.show', $invoice))->assertSee('45.00');
});

it('protects delivery and billing screens, prints and actions', function () {
    $d = app(App\Modules\Delivery\Services\Deliveries::class)->save(null, ['customer_id' => $this->customer->id, 'delivery_date' => '2026-11-10'], [['sales_order_line_id' => $this->line->id, 'quantity' => '10']]);
    $viewer = userWith(['deliveries.view']);

    $this->actingAs(userWith([]))->get(route('delivery.deliveries.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('delivery.deliveries.show', $d))->assertOk()->assertDontSee('Allocate stock');
    $this->actingAs($viewer)->get(route('delivery.invoices.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('delivery.deliveries.create'))->assertForbidden();

    Livewire::actingAs($viewer)->test(Deliveries\Show::class, ['delivery' => $d])->call('allocate')->assertForbidden();
    Livewire::actingAs($viewer)->test(Deliveries\Show::class, ['delivery' => $d])->call('dispatchDelivery')->assertForbidden();
    Livewire::actingAs(userWith(['invoices.view']))->test(Invoices\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['delivery_returns.view']))->test(Returns\Form::class)->call('save')->assertForbidden();
    Livewire::actingAs(userWith(['credit_notes.view']))->test(CreditNotes\Form::class)->call('save')->assertForbidden();
});

it('validates the delivery, invoice, return and credit note forms', function () {
    Livewire::test(Deliveries\Form::class)->call('save')->assertHasErrors(['customer_id' => 'required']);
    Livewire::test(Deliveries\Form::class)->set('customer_id', $this->customer->id)->call('save')->assertHasErrors('quantities');
    Livewire::test(Invoices\Form::class)->set('customer_id', $this->customer->id)->call('save')->assertHasErrors('lines');
    Livewire::test(Returns\Form::class)->call('save')->assertHasErrors(['customer_id', 'delivery_line_id', 'quantity', 'reason']);
    Livewire::test(CreditNotes\Form::class)->call('save')->assertHasErrors(['customer_id', 'reason', 'lines.0.description', 'lines.0.amount']);

    expect(DeliveryReturn::query()->count())->toBe(0)->and(CreditNote::query()->count())->toBe(0);
});
