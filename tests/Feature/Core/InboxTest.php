<?php

use App\Models\User;
use App\Modules\Catalog\Models\Item;
use App\Modules\Core\Livewire\Inbox\Bell;
use App\Modules\Core\Livewire\Inbox\Index as InboxScreen;
use App\Modules\Core\Notifications\SystemAlert;
use App\Modules\Core\Services\Alerts;
use App\Modules\Core\Services\ApprovalInbox;
use App\Modules\People\Models\Customer;
use App\Modules\Sales\Services\SalesOrders;
use Illuminate\Contracts\Queue\Job;
use Illuminate\Queue\Events\JobFailed;
use Livewire\Livewire;

beforeEach(function () {
    seedBase();
    $this->creator = userWith(['sales_orders.view', 'sales_orders.create', 'sales_orders.approve']);
    $this->manager = userWith(['sales_orders.view', 'sales_orders.approve']);
    $this->actingAs($this->creator);
    $customer = Customer::factory()->create(['currency_code' => 'USD']);
    $variant = Item::factory()->finished()->create()->variants()->first();
    $this->order = app(SalesOrders::class)->save(null, ['customer_id' => $customer->id, 'order_date' => '2026-11-01'], [[
        'item_variant_id' => $variant->id, 'description' => '', 'quantity' => '10', 'unit_price' => '5', 'tax_rate' => '', 'due_date' => null]]);
});

it('lists drafts awaiting approval for other approvers only, with a link to the document', function () {
    $inbox = app(ApprovalInbox::class);

    expect($inbox->count($this->creator))->toBe(0)
        ->and($inbox->for($this->manager)->pluck('number')->all())->toBe([$this->order->number])
        ->and($inbox->url($this->order))->toBe(route('sales.orders.show', $this->order));

    $this->actingAs($this->manager);
    Livewire::test(Bell::class)->assertSee('1 awaiting your approval');
    Livewire::test(InboxScreen::class)->assertSee($this->order->number)->assertSee('Sales Order');
    $this->get(route('core.inbox'))->assertOk();

    // A user who cannot approve sales orders sees none.
    expect($inbox->count(userWith(['sales_orders.view'])))->toBe(0);
});

it('shows the creator that someone else must approve', function () {
    $this->get(route('sales.orders.show', $this->order))->assertOk()->assertSee('Awaiting approval by another user');
    $this->actingAs($this->manager)->get(route('sales.orders.show', $this->order))->assertOk()->assertDontSee('Awaiting approval by another user');
});

it('sends alerts to administrators in the app, once per key until read', function () {
    $admin = admin();
    $alerts = app(Alerts::class);

    $alerts->toAdmins('Backup failed', 'Disk full', key: 'backup');
    $alerts->toAdmins('Backup failed', 'Disk full again', key: 'backup');

    expect($admin->unreadNotifications()->count())->toBe(1)
        ->and($admin->unreadNotifications()->first()->data)->toMatchArray(['title' => 'Backup failed', 'message' => 'Disk full', 'level' => 'danger'])
        ->and($this->manager->notifications()->count())->toBe(0);

    $this->actingAs($admin);
    Livewire::test(InboxScreen::class)->assertSee('Disk full')->call('markAllRead');
    expect($admin->unreadNotifications()->count())->toBe(0);

    $alerts->toAdmins('Backup failed', 'Still failing', key: 'backup');
    expect($admin->unreadNotifications()->count())->toBe(1);
});

it('raises an alert when a queued job fails', function () {
    $admin = admin();
    $job = Mockery::mock(Job::class)->shouldIgnoreMissing();
    $job->shouldReceive('resolveName')->andReturn('App\\Jobs\\ImportantJob');

    event(new JobFailed('database', $job, new RuntimeException('Out of memory')));

    expect($admin->unreadNotifications()->sole()->data['message'])->toBe('ImportantJob: Out of memory')
        ->and($admin->unreadNotifications()->sole()->type)->toBe(SystemAlert::class);
});
