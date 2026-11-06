<?php

use App\Modules\Core\Database\Seeders\CoreSeeder;
use App\Modules\Core\Models\Currency;
use App\Modules\Core\Models\Lookup;
use App\Modules\People\Livewire\Contractors;
use App\Modules\People\Livewire\Customers;
use App\Modules\People\Livewire\Employees;
use App\Modules\People\Livewire\Vendors;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Support\Documents\DocumentException;
use Livewire\Livewire;

beforeEach(fn () => $this->seed(CoreSeeder::class));

it('creates a customer with an automatic code and its own currency', function () {
    $this->actingAs(admin());

    Livewire::test(Customers\Form::class)
        ->set('name', 'Northwind Gloves Inc')
        ->set('currency_code', 'USD')
        ->set('country', 'United States')
        ->set('billing_address', '1 Main St')
        ->call('copyBillingToShipping')
        ->call('save')
        ->assertHasNoErrors();

    $customer = Customer::firstOrFail();
    expect($customer->code)->toBe('CUS-0001')
        ->and($customer->currency_code)->toBe('USD')
        ->and($customer->shipping_address)->toBe('1 Main St')
        ->and($customer->party->name)->toBe('Northwind Gloves Inc')
        ->and($customer->email)->toBeNull(); // unknown is NULL, not ''
});

it('requires an active currency for customers', function () {
    $this->actingAs(admin());
    Currency::where('code', 'EUR')->update(['is_active' => false]);

    Livewire::test(Customers\Form::class)
        ->set('name', 'Euro Buyer')->set('currency_code', 'EUR')->call('save')
        ->assertHasErrors(['currency_code']);
});

it('locks the customer currency once the customer is used', function () {
    $this->actingAs(admin());
    $customer = Customer::factory()->create(['currency_code' => 'USD']);
    markUsedWhen(Customer::class, fn (Customer $c) => $c->is($customer));

    Livewire::test(Customers\Form::class, ['customer' => $customer])
        ->set('currency_code', 'EUR')->call('save')
        ->assertHasErrors('currency_code');

    expect($customer->fresh()->currency_code)->toBe('USD')
        ->and(fn () => $customer->delete())->toThrow(DocumentException::class);
});

it('links a contractor to an existing vendor as the same business', function () {
    $this->actingAs(admin());
    $vendor = Vendor::factory()->create(['name' => 'Al-Noor Stitching']);

    Livewire::test(Contractors\Form::class)
        ->set('name', 'Al-Noor Stitching')
        ->set('work_types', ['Stitching'])
        ->set('linked_vendor_id', $vendor->id)
        ->call('save')
        ->assertHasNoErrors();

    $contractor = Contractor::firstOrFail();
    expect($contractor->party_id)->toBe($vendor->party_id)
        ->and($contractor->code)->toBe('CON-0001')
        ->and($contractor->work_types)->toBe(['Stitching']);

    $this->get(route('people.vendors.show', $vendor))->assertOk()->assertSee('Also a contractor')->assertSee($contractor->code);
});

it('creates salary and wage employees and validates the salary rule', function () {
    $this->actingAs(admin());
    $dept = Lookup::create(['type' => 'department', 'name' => 'Stitching Hall']);

    Livewire::test(Employees\Form::class)
        ->set('name', 'Imran Ali')->set('employee_type', 'salary')->set('join_date', '2026-01-10')->set('monthly_salary', '')
        ->call('save')->assertHasErrors(['monthly_salary']);

    Livewire::test(Employees\Form::class)
        ->set('name', 'Imran Ali')->set('employee_type', 'salary')->set('join_date', '2026-01-10')
        ->set('monthly_salary', '50000')->set('cnic', '35202-1234567-1')->set('department_id', $dept->id)
        ->call('save')->assertHasNoErrors();

    Livewire::test(Employees\Form::class)
        ->set('name', 'Bilal')->set('employee_type', 'wage')->set('join_date', '2026-02-01')
        ->call('save')->assertHasNoErrors();

    expect(Employee::where('name', 'Imran Ali')->value('monthly_salary'))->toBe('50000.00')
        ->and(Employee::where('name', 'Bilal')->first())->employee_type->toBe('wage')->monthly_salary->toBeNull();

    Livewire::test(Employees\Form::class)
        ->set('name', 'Dup')->set('join_date', '2026-01-01')->set('monthly_salary', '1')->set('cnic', '35202-1234567-1')
        ->call('save')->assertHasErrors(['cnic' => 'unique']);
});

it('hides and protects salaries from users without salary.view', function () {
    $employee = Employee::factory()->create(['monthly_salary' => '75000']);
    $this->actingAs(userWith(['employees.view', 'employees.edit']));

    $this->get(route('people.employees.index'))->assertOk()->assertDontSee('75,000');
    $this->get(route('people.employees.show', $employee))->assertOk()->assertDontSee('75,000');

    Livewire::test(Employees\Form::class, ['employee' => $employee])
        ->assertSet('monthly_salary', '')
        ->set('monthly_salary', '1')
        ->set('phone', '0300-1234567')
        ->call('save')
        ->assertHasNoErrors();

    expect($employee->fresh())->monthly_salary->toBe('75000.00')->phone->toBe('0300-1234567');

    $this->actingAs(userWith(['employees.view', 'salary.view']));
    $this->get(route('people.employees.show', $employee))->assertSee('75,000.00');
});

it('enforces people permissions', function () {
    $this->actingAs(userWith(['customers.view']));

    $this->get(route('people.customers.index'))->assertOk();
    $this->get(route('people.customers.create'))->assertForbidden();
    $this->get(route('people.vendors.index'))->assertForbidden();
    $this->get(route('people.employees.index'))->assertForbidden();

    Livewire::test(Customers\Form::class)->set('name', 'X')->set('currency_code', 'USD')->call('save')->assertForbidden();
});

it('lists, filters and exports each people type', function (string $component, string $factory) {
    $this->actingAs(admin());
    $factory::factory()->count(3)->create();
    $factory::factory()->create(['name' => 'Zeta Inactive', 'is_active' => false]);

    Livewire::test($component)->assertDontSee('Zeta Inactive')
        ->set('status', 'inactive')->assertSee('Zeta Inactive')
        ->call('export', 'csv')->assertFileDownloaded();
})->with([
    [Customers\Index::class, Customer::class],
    [Vendors\Index::class, Vendor::class],
    [Employees\Index::class, Employee::class],
    [Contractors\Index::class, Contractor::class],
]);

it('renders every people page for an admin', function (string $route, string $model) {
    $this->actingAs(admin());
    $record = $model::factory()->create();

    $this->get(route("people.$route.index"))->assertOk();
    $this->get(route("people.$route.create"))->assertOk();
    $this->get(route("people.$route.show", $record))->assertOk()->assertSee($record->code);
    $this->get(route("people.$route.edit", $record))->assertOk();
})->with([
    ['customers', Customer::class],
    ['vendors', Vendor::class],
    ['employees', Employee::class],
    ['contractors', Contractor::class],
]);
