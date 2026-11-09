<?php

namespace Database\Seeders;

use App\Models\User;
use App\Modules\Catalog\Models\Attribute;
use App\Modules\Catalog\Models\Item;
use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Catalog\Models\ProductionRoute;
use App\Modules\Catalog\Services\BomService;
use App\Modules\Catalog\Services\VariantGenerator;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Models\Unit;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Core\Services\Settings;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\CreditNotes;
use App\Modules\Delivery\Services\Deliveries;
use App\Modules\Delivery\Services\DeliveryReturns;
use App\Modules\Delivery\Services\Invoices;
use App\Modules\Delivery\Services\Packing;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Finance\Services\MoneyMovements;
use App\Modules\Finance\Services\Receipts;
use App\Modules\Inventory\Actions\PostStockAdjustment;
use App\Modules\Inventory\Models\Location;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Services\Attendance;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Modules\Payroll\Services\Payrolls;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\WageWork;
use App\Modules\Production\Services\ContractorJobs;
use App\Modules\Production\Services\Ptcs;
use App\Modules\Production\Services\PtcTransactions;
use App\Modules\Production\Services\WageWorks;
use App\Modules\Production\Services\Worker;
use App\Modules\Purchasing\Actions\PostGoodsReceipt;
use App\Modules\Purchasing\Actions\PostPurchaseReturn;
use App\Modules\Purchasing\Actions\PostVendorBill;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Modules\Purchasing\Models\VendorBill;
use App\Modules\Purchasing\Services\PurchaseOrders;
use App\Modules\Sales\Models\SalesOrder;
use App\Modules\Sales\Services\SalesOrders;
use App\Support\Decimal;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Sample company for viewing and testing the software locally. Not part of
 * DatabaseSeeder, so it never reaches a real deployment:
 *
 *   php artisan db:seed --class=DemoDataSeeder
 *
 * Documents go through the real services and posting actions, so stock,
 * journals and ledgers agree. Everything runs in one transaction: a failure
 * leaves the database as it was. Strict mode stays on, so a missing eager load
 * in a service fails here exactly as it would on screen
 * (tests/Feature/Foundation/DemoDataTest.php runs this seeder and opens every page).
 */
class DemoDataSeeder extends Seeder
{
    public const PASSWORD = 'Demo@12345';

    private const USERS = [
        'manager@demo.test' => ['Maria Manager', 'Manager'],
        'sales@demo.test' => ['Sana Sales', 'Sales'],
        'purchase@demo.test' => ['Pervez Purchase', 'Purchase'],
        'store@demo.test' => ['Salman Store', 'Store'],
        'production@demo.test' => ['Parveen Production', 'Production'],
        'accountant@demo.test' => ['Asif Accountant', 'Accountant'],
        'hr@demo.test' => ['Hina HR', 'HR'],
    ];

    private DocumentWorkflow $workflow;

    private DocumentNumber $numbers;

    /** @var array<string, ItemVariant> */
    private array $variants = [];

    /** @var array<string, Item> */
    private array $items = [];

    /** @var array<string, ProductionRoute> */
    private array $routes = [];

    /** @var array<string, Customer> */
    private array $customers = [];

    /** @var array<string, Vendor> */
    private array $vendors = [];

    /** @var array<string, Employee> */
    private array $employees = [];

    /** @var array<string, Contractor> */
    private array $contractors = [];

    /** @var array<string, MoneyAccount> */
    private array $money = [];

    /** @var array<string, SalesOrder> */
    private array $orders = [];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('Demo data must not be loaded in production.');
        }

        if (User::query()->where('email', array_key_first(self::USERS))->exists()) {
            $this->command?->warn('Demo data is already loaded. Nothing was changed.');

            return;
        }

        $admin = User::role(SyncPermissions::ADMIN)->orderBy('id')->first();
        if ($admin === null) {
            throw new RuntimeException('Run the base seeders first: php artisan db:seed');
        }

        // Services read auth() for permissions and created_by.
        Auth::guard()->setUser($admin);
        $this->workflow = app(DocumentWorkflow::class);
        $this->numbers = app(DocumentNumber::class);

        DB::transaction(function () {
            $this->seedUsers();
            $this->seedCompany();
            $this->seedParties();
            $this->seedItems();
            $this->seedMoney();
            $this->seedPurchasing();
            $this->seedSalesOrders();
            $this->seedProduction();
            $this->seedDeliveries();
            $this->seedFinance();
            $this->seedPayroll();
        });

        $this->command?->info('Demo data loaded. Every demo user has the password: '.self::PASSWORD);
        $this->command?->table(['Email', 'Role'], collect(self::USERS)->map(fn ($u, $email) => [$email, $u[1]])->values()->all());
    }

    private function seedUsers(): void
    {
        foreach (self::USERS as $email => [$name, $role]) {
            User::query()->create(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'is_active' => true])->assignRole($role);
        }
    }

    private function seedCompany(): void
    {
        $settings = app(Settings::class);
        if ($settings->get('company.name') === 'My Company') {
            $settings->setMany([
                'company.name' => 'Demo Sports Industries (Pvt) Ltd',
                'company.address' => 'Plot 12, Small Industrial Estate, Sialkot',
                'company.phone' => '+92 52 0000000',
                'company.email' => 'info@demo.test',
                'company.ntn' => '0000000-0',
            ]);
        }

        foreach ([
            'department' => ['Production', 'Stores', 'Accounts', 'Sales', 'Human Resources'],
            'designation' => ['Supervisor', 'Cutter', 'Stitcher', 'Packer', 'Accounts Officer', 'Store Keeper'],
            'item_category' => ['Leather', 'Fabric', 'Trims', 'Packaging', 'Ball Components', 'Gloves', 'Balls'],
        ] as $type => $names) {
            foreach ($names as $i => $name) {
                Lookup::query()->firstOrCreate(['type' => $type, 'name' => $name], ['sort_order' => $i * 10]);
            }
        }

        foreach (['Cutting' => 5, 'Packing' => 100] as $name => $order) {
            Stage::query()->firstOrCreate(['name' => $name], ['sort_order' => $order]);
        }
    }

    private function seedParties(): void
    {
        foreach ([
            'northfield' => ['Northfield Sports Inc', 'USD', 'United States', 'orders@northfield.demo.test', 'Net 30'],
            'rheinland' => ['Rheinland Sportartikel GmbH', 'EUR', 'Germany', 'einkauf@rheinland.demo.test', 'Net 45'],
            'albion' => ['Albion Fight Gear Ltd', 'GBP', 'United Kingdom', 'buying@albion.demo.test', 'Net 30'],
            'gulf' => ['Gulf Athletics Trading LLC', 'AED', 'United Arab Emirates', 'sales@gulf.demo.test', 'Advance'],
            'lahore' => ['Lahore Sports Mart', 'PKR', 'Pakistan', 'lahore@sportsmart.demo.test', 'Net 15'],
        ] as $key => [$name, $currency, $country, $email, $terms]) {
            $this->customers[$key] = Customer::query()->create(['name' => $name, 'currency_code' => $currency, 'country' => $country, 'email' => $email, 'payment_terms' => $terms, 'is_active' => true]);
        }
        Customer::query()->create(['name' => 'Old Trading Co (inactive)', 'currency_code' => 'USD', 'country' => 'Canada', 'is_active' => false]);

        foreach ([
            'leather' => ['Sialkot Leather Traders', 'Imran Butt'],
            'trims' => ['Punjab Thread and Trims', 'Bilal Ahmed'],
            'balls' => ['Ball Components Co', 'Usman Ghani'],
            'utility' => ['City Power Services', 'Billing Desk'],
        ] as $key => [$name, $contact]) {
            $this->vendors[$key] = Vendor::query()->create(['name' => $name, 'contact_person' => $contact, 'phone' => '+92 300 0000000', 'payment_terms' => 'Net 30', 'is_active' => true]);
        }

        foreach ([
            'stitching' => ['Rafiq Stitching Unit', ['Stitching']],
            'balls' => ['Yaqoob Ball Workshop', ['Ball Stitching']],
        ] as $key => [$name, $work]) {
            $this->contractors[$key] = Contractor::query()->create(['name' => $name, 'phone' => '+92 321 0000000', 'work_types' => $work, 'is_active' => true]);
        }

        $department = fn (string $name) => Lookup::query()->where('type', 'department')->where('name', $name)->value('id');
        $designation = fn (string $name) => Lookup::query()->where('type', 'designation')->where('name', $name)->value('id');

        foreach ([
            'supervisor' => ['Tariq Mehmood', Employee::TYPE_SALARY, '120000', 'Production', 'Supervisor'],
            'cutter' => ['Nadeem Akhtar', Employee::TYPE_SALARY, '65000', 'Production', 'Cutter'],
            'packer' => ['Shahid Iqbal', Employee::TYPE_SALARY, '50000', 'Production', 'Packer'],
            'storekeeper' => ['Waseem Abbas', Employee::TYPE_SALARY, '55000', 'Stores', 'Store Keeper'],
            'clerk' => ['Rizwan Saeed', Employee::TYPE_SALARY, '75000', 'Accounts', 'Accounts Officer'],
            'stitcher' => ['Karim Baksh', Employee::TYPE_WAGE, null, 'Production', 'Stitcher'],
            'stitcher2' => ['Javed Hussain', Employee::TYPE_WAGE, null, 'Production', 'Stitcher'],
        ] as $key => [$name, $type, $salary, $dept, $title]) {
            $this->employees[$key] = Employee::query()->create([
                'name' => $name, 'employee_type' => $type, 'monthly_salary' => $salary, 'join_date' => '2024-03-01',
                'department_id' => $department($dept), 'designation_id' => $designation($title), 'phone' => '+92 333 0000000', 'is_active' => true,
            ]);
        }
    }

    private function seedItems(): void
    {
        $category = fn (string $name) => Lookup::query()->where('type', 'item_category')->where('name', $name)->value('id');
        $unit = fn (string $symbol) => Unit::query()->where('symbol', $symbol)->value('id');

        foreach ([
            'leather' => ['RM-LEA', 'Cow Leather', 'kg', 'Leather', '1800', '100'],
            'lining' => ['RM-LIN', 'Lining Fabric', 'm', 'Fabric', '350', '150'],
            'thread' => ['RM-THR', 'Polyester Thread Cone', 'pcs', 'Trims', '220', '30'],
            'velcro' => ['RM-VEL', 'Velcro Strap', 'm', 'Trims', '60', '100'],
            'carton' => ['RM-CTN', 'Export Carton', 'pcs', 'Packaging', '120', '50'],
            'panel' => ['RM-PAN', 'Ball Panel', 'pcs', 'Ball Components', '18', '2000'],
            'bladder' => ['RM-BLD', 'Latex Bladder', 'pcs', 'Ball Components', '95', '100'],
        ] as $key => [$code, $name, $symbol, $cat, $cost, $min]) {
            $this->items[$key] = Item::query()->create([
                'code' => $code, 'name' => $name, 'type' => Item::RAW_MATERIAL, 'stock_unit_id' => $unit($symbol), 'category_id' => $category($cat),
                'default_purchase_cost' => $cost, 'min_stock' => $min, 'can_buy' => true, 'is_active' => true,
            ]);
            $this->variants[$key] = $this->items[$key]->variants()->firstOrFail();
        }

        foreach ([
            'boxing' => ['FG-BOX', 'Pro Boxing Glove', 'pair', 'Gloves', '30'],
            'keeper' => ['FG-GKP', 'Goalkeeper Glove', 'pair', 'Gloves', '24'],
            'football' => ['FG-FTB', 'Match Football', 'pcs', 'Balls', '15'],
        ] as $key => [$code, $name, $symbol, $cat, $price]) {
            $this->items[$key] = Item::query()->create([
                'code' => $code, 'name' => $name, 'type' => Item::FINISHED_GOOD, 'stock_unit_id' => $unit($symbol), 'category_id' => $category($cat),
                'default_selling_price' => $price, 'can_make' => true, 'can_sell' => true, 'can_buy' => false, 'is_active' => true,
            ]);
            $this->variants[$key] = $this->items[$key]->variants()->firstOrFail();
        }

        // Boxing gloves come in three weights, so the catalogue shows real variants.
        $weight = Attribute::query()->create(['name' => 'Weight']);
        $values = collect(['10oz', '12oz', '16oz'])->map(fn ($value) => $weight->values()->create(['value' => $value])->id)->all();
        app(VariantGenerator::class)->generate($this->items['boxing'], [$weight->id => $values]);
        foreach ($this->items['boxing']->variants()->get() as $variant) {
            $this->variants['boxing'.$variant->label] = $variant;
        }
        unset($this->variants['boxing']);

        $this->makeRouteAndBom('boxing', [['leather', '0.35', 'kg'], ['lining', '0.4', 'm'], ['thread', '0.02', 'pcs']], true);
        $this->makeRouteAndBom('keeper', [['leather', '0.15', 'kg'], ['velcro', '0.3', 'm'], ['thread', '0.01', 'pcs']], false);
    }

    /** @param list<array{string, string, string}> $components */
    private function makeRouteAndBom(string $item, array $components, bool $embossing): void
    {
        $route = ProductionRoute::query()->create(['item_id' => $this->items[$item]->id, 'name' => 'Standard', 'is_default' => true, 'is_active' => true]);

        foreach (array_values(array_filter(['Cutting', 'Stitching', $embossing ? 'Logo Embossing' : null, 'Packing'])) as $i => $name) {
            $route->stages()->create([
                'stage_id' => Stage::query()->where('name', $name)->value('id'),
                'sequence' => $i + 1,
                'is_optional' => $name === 'Logo Embossing',
                'wage_rate' => $name === 'Stitching' ? '45' : null,
                'contractor_rate' => $name === 'Stitching' ? '55' : null,
            ]);
        }
        $this->routes[$item] = $route;

        $bom = app(BomService::class)->newVersion($this->items[$item]);
        foreach ($components as [$component, $quantity, $symbol]) {
            $bom->lines()->create(['component_item_id' => $this->items[$component]->id, 'quantity' => $quantity, 'unit_id' => Unit::query()->where('symbol', $symbol)->value('id'), 'wastage_percent' => 0]);
        }
        app(BomService::class)->approve($bom);
    }

    private function seedMoney(): void
    {
        $accounts = app(MoneyAccounts::class);
        $this->money['cash'] = MoneyAccount::cashBook();
        $this->money['pkr'] = $accounts->create(['name' => 'Meezan Bank PKR', 'type' => MoneyAccount::BANK, 'currency_code' => 'PKR', 'bank_name' => 'Meezan Bank', 'account_number' => '0000-0000001']);
        $this->money['usd'] = $accounts->create(['name' => 'Meezan Bank USD', 'type' => MoneyAccount::BANK, 'currency_code' => 'USD', 'bank_name' => 'Meezan Bank', 'account_number' => '0000-0000002']);
        $this->money['eur'] = $accounts->create(['name' => 'Meezan Bank EUR', 'type' => MoneyAccount::BANK, 'currency_code' => 'EUR', 'bank_name' => 'Meezan Bank', 'account_number' => '0000-0000003']);

        $moves = app(MoneyMovements::class);
        $moves->postVoucher($this->workflow->approve($moves->saveVoucher(null, ['voucher_date' => '2026-09-01', 'description' => 'Owner capital introduced'], [
            ['account' => 'money:'.$this->money['pkr']->id, 'debit' => '8000000'],
            ['account' => 'money:'.$this->money['cash']->id, 'debit' => '500000'],
            ['account' => 'gl:'.GlAccount::system('owner_capital')->id, 'credit' => '8500000'],
        ])));
    }

    private function seedPurchasing(): void
    {
        $orders = app(PurchaseOrders::class);

        // Fully received, billed with freight and paid; part returned to the vendor.
        $po = $this->purchaseOrder('leather', '2026-09-02', [['leather', '300', 'kg', '1800'], ['lining', '400', 'm', '350']]);
        $grn = $this->receiveGoods($po, '2026-09-08', [['300', '300', '0'], ['400', '400', '0']]);
        $bill = $this->bill($grn, 'SLT-4471', '2026-09-10', '0', '15000');
        $this->pay('vendor', $this->vendors['leather'], '2026-09-25', (string) $bill->total, 'pkr', [['allocatable' => $bill, 'amount' => (string) $bill->total]]);

        $line = $grn->lines()->orderBy('id')->firstOrFail();
        $return = PurchaseReturn::query()->create([
            'number' => $this->numbers->next('purchase_return', Carbon::parse('2026-09-15')), 'vendor_id' => $grn->vendor_id, 'goods_receipt_id' => $grn->id,
            'return_date' => '2026-09-15', 'reason' => 'Hides scratched', 'replacement_required' => false, 'total' => '18000',
        ]);
        $return->lines()->create([
            'goods_receipt_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'lot_id' => $line->lot_id, 'status' => 'available',
            'location_id' => Location::mainStore()->id, 'quantity' => '10', 'rate' => '1800', 'amount' => '18000',
        ]);
        $this->workflow->approve($return);
        app(PostPurchaseReturn::class)($return);

        // Received and billed with tax, part paid: leaves a payable.
        $po = $this->purchaseOrder('trims', '2026-09-03', [['thread', '200', 'pcs', '220'], ['velcro', '600', 'm', '60'], ['carton', '300', 'pcs', '120']]);
        $grn = $this->receiveGoods($po, '2026-09-09', [['200', '200', '0'], ['600', '600', '0'], ['300', '300', '0']]);
        $bill = $this->bill($grn, 'PTT-1092', '2026-09-12', '21240', '0');
        $this->pay('vendor', $this->vendors['trims'], '2026-10-05', '80000', 'pkr', [['allocatable' => $bill, 'amount' => '80000']]);

        // Partly received, some rejected and sent back, not billed yet.
        $po = $this->purchaseOrder('balls', '2026-09-20', [['panel', '12000', 'pcs', '18'], ['bladder', '400', 'pcs', '95']]);
        $reason = Lookup::query()->where('type', 'rejection_reason')->value('id');
        $this->receiveGoods($po, '2026-09-28', [
            ['10000', '9900', '100', ['rejected_action' => 'return', 'reject_reason_id' => $reason]],
            ['400', '400', '0'],
        ]);

        // One sent and waiting, one draft, one cancelled.
        $sent = $this->purchaseOrder('leather', '2026-11-02', [['leather', '150', 'kg', '1850']]);
        $orders->markSent($sent);
        $this->purchaseOrder('trims', '2026-11-05', [['velcro', '500', 'm', '62']], approve: false);
        $orders->cancel($this->purchaseOrder('balls', '2026-10-12', [['bladder', '100', 'pcs', '99']]), 'Rate not agreed');
    }

    /** @param list<array{string, string, string, string}> $lines */
    private function purchaseOrder(string $vendor, string $date, array $lines, bool $approve = true): PurchaseOrder
    {
        $po = app(PurchaseOrders::class)->saveDraft(null, ['vendor_id' => $this->vendors[$vendor]->id, 'order_date' => $date], array_map(fn ($l) => [
            'item_variant_id' => $this->variants[$l[0]]->id, 'stage_id' => null, 'description' => '', 'quantity' => $l[1],
            'unit_id' => Unit::query()->where('symbol', $l[2])->value('id'), 'rate' => $l[3], 'expected_date' => null,
        ], $lines));

        return $approve ? $this->workflow->approve($po) : $po;
    }

    /** @param list<array<int, mixed>> $receipts received, accepted, rejected, extra columns; one per order line */
    private function receiveGoods(PurchaseOrder $po, string $date, array $receipts): GoodsReceipt
    {
        $grn = GoodsReceipt::query()->create(['number' => $this->numbers->next('goods_receipt', Carbon::parse($date)), 'purchase_order_id' => $po->id, 'vendor_id' => $po->vendor_id, 'receipt_date' => $date]);

        foreach ($po->lines()->orderBy('id')->get()->values() as $i => $line) {
            $grn->lines()->create(($receipts[$i][3] ?? []) + [
                'purchase_order_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'ordered_qty' => $line->quantity,
                'previously_accepted_qty' => $line->accepted_qty, 'received_qty' => $receipts[$i][0], 'accepted_qty' => $receipts[$i][1], 'rejected_qty' => $receipts[$i][2],
                'rate' => $line->rate, 'location_id' => Location::mainStore()->id,
            ]);
        }
        $this->workflow->approve($grn);

        return app(PostGoodsReceipt::class)($grn);
    }

    private function bill(GoodsReceipt $grn, string $vendorInvoice, string $date, string $tax, string $charges): VendorBill
    {
        $lines = $grn->lines()->orderBy('id')->get();
        $goods = $lines->reduce(fn (string $sum, $l) => Decimal::add($sum, Decimal::mul($l->accepted_qty, $l->rate)), '0');

        $bill = VendorBill::query()->create([
            'number' => $this->numbers->next('vendor_bill', Carbon::parse($date)), 'vendor_id' => $grn->vendor_id, 'vendor_invoice_number' => $vendorInvoice, 'bill_date' => $date,
            'goods_amount' => $goods, 'tax_amount' => $tax, 'charges_amount' => $charges, 'deductions_amount' => '0', 'total' => Decimal::add(Decimal::add($goods, $tax), $charges),
        ]);
        foreach ($lines as $line) {
            $bill->lines()->create(['goods_receipt_line_id' => $line->id, 'item_variant_id' => $line->item_variant_id, 'quantity' => $line->accepted_qty, 'rate' => $line->rate, 'amount' => Decimal::mul($line->accepted_qty, $line->rate)]);
        }
        $this->workflow->approve($bill);
        app(PostVendorBill::class)($bill);

        return $bill->fresh();
    }

    /** @param list<array{allocatable: mixed, amount: string}> $allocations */
    private function pay(string $kind, Vendor|Contractor|Employee $payee, string $date, string $amount, string $account, array $allocations = []): Payment
    {
        $payment = Payment::query()->create([
            'number' => $this->numbers->next('payment', Carbon::parse($date)), 'payment_date' => $date, 'payee_kind' => $kind,
            'payee_type' => $payee->getMorphClass(), 'payee_id' => $payee->id, 'money_account_id' => $this->money[$account]->id, 'amount' => $amount,
        ]);

        return app(PostPayment::class)($this->workflow->approve($payment), $allocations);
    }

    private function seedSalesOrders(): void
    {
        $this->orders['shipped'] = $this->salesOrder('northfield', '2026-09-18', [['boxing12oz', '200', '32'], ['football', '100', '15']], 'NF-PO-8812');
        $this->orders['partial'] = $this->salesOrder('rheinland', '2026-10-05', [['boxing12oz', '96', '29'], ['football', '148', '14']], 'RS-20417');
        $this->orders['packed'] = $this->salesOrder('albion', '2026-10-01', [['boxing16oz', '200', '30']], 'ALB-3321');
        $this->orders['domestic'] = $this->salesOrder('lahore', '2026-10-20', [['keeper', '150', '3200']], 'LSM-77', '18');
        $this->orders['inproduction'] = $this->salesOrder('northfield', '2026-11-01', [['boxing10oz', '150', '31']], 'NF-PO-9034');
        $this->salesOrder('gulf', '2026-11-04', [['football', '60', '58']], 'GA-118', approve: false);

        app(SalesOrders::class)->cancel($this->salesOrder('northfield', '2026-10-15', [['football', '40', '15']], 'NF-PO-8950'), 'Customer changed the specification');
    }

    /** @param list<array{string, string, string}> $lines */
    private function salesOrder(string $customer, string $date, array $lines, string $po, string $tax = '', bool $approve = true): SalesOrder
    {
        $order = app(SalesOrders::class)->save(null, ['customer_id' => $this->customers[$customer]->id, 'order_date' => $date, 'customer_po_number' => $po], array_map(fn ($l) => [
            'item_variant_id' => $this->variants[$l[0]]->id, 'description' => '', 'quantity' => $l[1], 'unit_price' => $l[2], 'tax_rate' => $tax, 'due_date' => null,
        ], $lines));

        return $approve ? $this->workflow->approve($order) : $order;
    }

    private function worker(string $employee): Worker
    {
        return Worker::from('employee', $this->employees[$employee]->id, null);
    }

    private function seedProduction(): void
    {
        $tx = app(PtcTransactions::class);
        $ptcs = app(Ptcs::class);

        // Made for stock: finished, with a few pieces rejected at stitching.
        $ptc = $this->ptc('boxing12oz', 'boxing', '300', '2026-09-10');
        $this->issueMaterials($ptc, [['leather', '105'], ['lining', '120'], ['thread', '6']], '2026-09-10');
        $this->runAllStages($ptc, '300', '4', '2026-09-12');
        $tx->release($ptc->fresh(), '296', Carbon::parse('2026-09-16'));
        $ptcs->complete($ptc->fresh());

        // Made against an order: the release reserves the pieces to that order line.
        $ptc = $this->ptc('boxing16oz', 'boxing', '200', '2026-10-06', $this->orders['packed']);
        $this->issueMaterials($ptc, [['leather', '70'], ['lining', '80'], ['thread', '4']], '2026-10-06');
        $this->runAllStages($ptc, '200', '0', '2026-10-08');
        $tx->release($ptc->fresh(), '200', Carbon::parse('2026-10-14'));
        $ptcs->complete($ptc->fresh());

        foreach (WageWork::query()->where('status', 'pending')->get() as $work) {
            app(WageWorks::class)->approve($work);
        }

        // Still on the floor: stitching is half done and its wage work waits for review.
        $ptc = $this->ptc('boxing10oz', 'boxing', '150', '2026-11-03', $this->orders['inproduction']);
        $this->issueMaterials($ptc, [['leather', '52.5'], ['lining', '60'], ['thread', '3']], '2026-11-03');
        $stages = $ptc->stages()->get()->keyBy(fn ($s) => $s->stage->name);
        $day = Carbon::parse('2026-11-05');
        $tx->issueStage($ptc, $stages['Cutting'], '150', $this->worker('cutter'), $day);
        $tx->receiveStage($ptc, $stages['Cutting'], $this->worker('cutter'), '150', '0', $day);
        $tx->issueStage($ptc, $stages['Stitching'], '150', $this->worker('stitcher2'), $day);
        $tx->receiveStage($ptc, $stages['Stitching'], $this->worker('stitcher2'), '80', '0', Carbon::parse('2026-11-07'));

        // Approved and waiting to start, and a draft.
        $this->ptc('keeper', 'keeper', '150', '2026-11-06', $this->orders['domestic']);
        $this->ptc('keeper', 'keeper', '100', '2026-11-08', approve: false);

        // Footballs made outside by a contractor, part delivered back.
        $jobs = app(ContractorJobs::class);
        $job = $jobs->approve($jobs->save(null, ['contractor_id' => $this->contractors['balls']->id, 'job_date' => '2026-09-29'],
            [['item_variant_id' => $this->variants['football']->id, 'stage_id' => null, 'quantity' => '300', 'rate' => '85']],
            [['item_variant_id' => $this->variants['panel']->id, 'expected_qty' => '32'], ['item_variant_id' => $this->variants['bladder']->id, 'expected_qty' => '1']]));
        $jobs->issueMaterial($job, $this->variants['panel']->id, '9600', Carbon::parse('2026-09-30'));
        $jobs->issueMaterial($job, $this->variants['bladder']->id, '300', Carbon::parse('2026-09-30'));
        $jobs->receive($job->fresh(), $job->lines()->firstOrFail(), '250', '248', '2', Carbon::parse('2026-10-02'), rejectReason: 'Seams open');
        $this->pay('contractor', $this->contractors['balls'], '2026-10-12', '15000', 'cash');
    }

    private function ptc(string $variant, string $route, string $quantity, string $date, ?SalesOrder $order = null, bool $approve = true): Ptc
    {
        $ptc = app(Ptcs::class)->save(null, [
            'item_variant_id' => $this->variants[$variant]->id, 'planned_qty' => $quantity, 'route_id' => $this->routes[$route]->id, 'start_date' => $date,
            'sales_order_line_id' => $order?->lines()->orderBy('id')->value('id'), 'responsible_employee_id' => $this->employees['supervisor']->id,
        ]);

        return $approve ? app(Ptcs::class)->approve($ptc) : $ptc;
    }

    /** @param list<array{string, string}> $materials */
    private function issueMaterials(Ptc $ptc, array $materials, string $date): void
    {
        foreach ($materials as [$material, $quantity]) {
            app(PtcTransactions::class)->issueMaterial($ptc, $ptc->stages()->orderBy('id')->firstOrFail(), $this->variants[$material]->id, $quantity, $this->worker('cutter'), Carbon::parse($date));
        }
    }

    private function runAllStages(Ptc $ptc, string $quantity, string $rejected, string $date): void
    {
        $tx = app(PtcTransactions::class);
        $day = Carbon::parse($date);
        $stages = $ptc->stages()->get()->keyBy(fn ($s) => $s->stage->name);
        $good = Decimal::sub($quantity, $rejected, 4);

        $tx->issueStage($ptc, $stages['Cutting'], $quantity, $this->worker('cutter'), $day);
        $tx->receiveStage($ptc, $stages['Cutting'], $this->worker('cutter'), $quantity, '0', $day);
        $tx->issueStage($ptc, $stages['Stitching'], $quantity, $this->worker('stitcher'), $day);
        $tx->receiveStage($ptc, $stages['Stitching'], $this->worker('stitcher'), $good, $rejected, $day, $rejected !== '0' ? 'Stitching defect' : null);
        $tx->skipStage($ptc, $stages['Logo Embossing'], 'Plain model', $day);
        $tx->issueStage($ptc, $stages['Packing'], $good, $this->worker('packer'), $day);
        $tx->receiveStage($ptc, $stages['Packing'], $this->worker('packer'), $good, '0', $day);
    }

    private function seedDeliveries(): void
    {
        $receipts = app(Receipts::class);

        // Shipped in full, invoiced, paid in full less bank charges.
        $shipped = $this->orders['shipped']->lines()->orderBy('id')->get();
        $delivery = $this->dispatch('northfield', '2026-10-03', [[$shipped[0]->id, '200'], [$shipped[1]->id, '100']]);
        $invoice = $this->invoice('northfield', '2026-10-04', $delivery);
        $receipts->post($this->workflow->approve($receipts->save(null, ['receipt_date' => '2026-10-15', 'customer_id' => $this->customers['northfield']->id, 'amount' => (string) $invoice->total, 'money_account_id' => $this->money['usd']->id],
            [['gl_account_id' => GlAccount::system('bank_charges')->id, 'amount' => '50']],
            [['type' => $invoice->getMorphClass(), 'id' => $invoice->id, 'amount' => (string) $invoice->total]])));

        // Advance from the same customer, not yet set against an invoice.
        $receipts->post($this->workflow->approve($receipts->save(null, ['receipt_date' => '2026-11-03', 'customer_id' => $this->customers['northfield']->id, 'amount' => '1500', 'money_account_id' => $this->money['usd']->id], [], [])));

        // Part shipped and part paid, with a return and a credit note.
        $partial = $this->orders['partial']->lines()->orderBy('id')->get();
        $delivery = $this->dispatch('rheinland', '2026-10-18', [[$partial[0]->id, '96'], [$partial[1]->id, '100']]);
        $invoice = $this->invoice('rheinland', '2026-10-19', $delivery);
        $receipts->post($this->workflow->approve($receipts->save(null, ['receipt_date' => '2026-11-02', 'customer_id' => $this->customers['rheinland']->id, 'amount' => '2000', 'money_account_id' => $this->money['eur']->id], [],
            [['type' => $invoice->getMorphClass(), 'id' => $invoice->id, 'amount' => '2000']])));

        $returns = app(DeliveryReturns::class);
        $return = $returns->post($this->workflow->approve($returns->save(null, ['delivery_line_id' => $delivery->lines()->orderBy('id')->value('id'), 'quantity' => '6', 'reason' => 'Wrong weight packed', 'return_date' => '2026-10-28'])));
        $day = Carbon::parse('2026-10-30');
        $returns->restock($return->fresh(), '4', null, $day);
        $returns->reject($return->fresh(), '2', 'Stitching open', $day);

        $notes = app(CreditNotes::class);
        $notes->post($this->workflow->approve($notes->save(null, ['customer_id' => $this->customers['rheinland']->id, 'invoice_id' => $invoice->id, 'credit_date' => '2026-10-31', 'reason' => 'Returned pairs'], [['description' => '6 pairs returned', 'amount' => '174']])));

        // Packed and ready to dispatch.
        $packed = app(Deliveries::class)->allocate(app(Deliveries::class)->save(null, ['customer_id' => $this->customers['albion']->id, 'delivery_date' => '2026-11-08'],
            [['sales_order_line_id' => $this->orders['packed']->lines()->orderBy('id')->value('id'), 'quantity' => '200']]));
        $this->packInCartons($packed);
    }

    /** @param list<array{int, string}> $lines */
    private function dispatch(string $customer, string $date, array $lines): Delivery
    {
        $deliveries = app(Deliveries::class);
        $delivery = $deliveries->allocate($deliveries->save(null, ['customer_id' => $this->customers[$customer]->id, 'delivery_date' => $date],
            array_map(fn ($l) => ['sales_order_line_id' => $l[0], 'quantity' => $l[1]], $lines)));
        $this->packInCartons($delivery);

        return $deliveries->dispatch($delivery->fresh());
    }

    private function packInCartons(Delivery $delivery): void
    {
        foreach ($delivery->lines as $line) {
            app(Packing::class)->addCarton($delivery, ['carton_type' => 'Master', 'gross_weight' => '18', 'net_weight' => '16.5'], [$line->id => $line->quantity]);
        }
        app(Packing::class)->complete($delivery);
    }

    private function invoice(string $customer, string $date, Delivery $delivery): Invoice
    {
        $invoices = app(Invoices::class);

        return $invoices->post($this->workflow->approve($invoices->save(null, ['customer_id' => $this->customers[$customer]->id, 'invoice_date' => $date],
            $delivery->lines()->orderBy('id')->pluck('id')->map(fn ($id) => ['delivery_line_id' => $id])->all())));
    }

    private function seedFinance(): void
    {
        $moves = app(MoneyMovements::class);

        $moves->postConversion($this->workflow->approve($moves->saveConversion(null, ['conversion_date' => '2026-10-20', 'from_account_id' => $this->money['usd']->id, 'from_amount' => '5000', 'to_account_id' => $this->money['pkr']->id, 'pkr_received' => '1395000'])));
        $moves->postTransfer($this->workflow->approve($moves->saveTransfer(null, ['transfer_date' => '2026-10-01', 'from_account_id' => $this->money['pkr']->id, 'to_account_id' => $this->money['cash']->id, 'amount' => '300000'])));
        $this->workflow->approve($moves->saveTransfer(null, ['transfer_date' => '2026-11-07', 'from_account_id' => $this->money['pkr']->id, 'to_account_id' => $this->money['cash']->id, 'amount' => '100000']));

        $rent = GlAccount::system('rent')->id;
        foreach (['2026-09-05', '2026-10-05', '2026-11-05'] as $date) {
            $moves->postExpense($this->workflow->approve($moves->saveExpense(null, ['expense_date' => $date, 'expense_account_id' => $rent, 'money_account_id' => $this->money['pkr']->id, 'amount' => '150000'])));
        }

        $power = $moves->postExpense($this->workflow->approve($moves->saveExpense(null, ['expense_date' => '2026-10-10', 'expense_account_id' => GlAccount::system('utilities')->id, 'vendor_id' => $this->vendors['utility']->id, 'amount' => '48500'])));
        $this->pay('vendor', $this->vendors['utility'], '2026-10-22', '48500', 'cash', [['allocatable' => $power, 'amount' => '48500']]);
        $moves->saveExpense(null, ['expense_date' => '2026-11-09', 'expense_account_id' => GlAccount::system('other_expenses')->id, 'money_account_id' => $this->money['cash']->id, 'amount' => '7500']);

        // A fine taken through payroll.
        $stitcher = $this->employees['stitcher'];
        $moves->postVoucher($this->workflow->approve($moves->saveVoucher(null, ['voucher_date' => '2026-10-20', 'description' => 'Damaged leather panels'], [
            ['account' => 'gl:'.GlAccount::system('wages_payable')->id, 'party' => $stitcher->getMorphClass().':'.$stitcher->id, 'debit' => '1500', 'deduct_from_payroll' => true],
            ['account' => 'gl:'.GlAccount::system('damage_recoveries')->id, 'credit' => '1500'],
        ])));

        // Physical count found less leather than the books.
        $adjustment = StockAdjustment::query()->create([
            'number' => $this->numbers->next('stock_adjustment', Carbon::parse('2026-10-31')), 'adjustment_date' => '2026-10-31',
            'reason_id' => Lookup::query()->where('type', 'adjustment_reason')->value('id'), 'notes' => 'Month-end count',
        ]);
        $adjustment->lines()->create(['item_variant_id' => $this->variants['leather']->id, 'stage_id' => null, 'status' => 'available', 'location_id' => Location::mainStore()->id, 'lot_id' => null, 'quantity' => '-2', 'unit_cost' => null]);
        $this->workflow->approve($adjustment);
        app(PostStockAdjustment::class)($adjustment);
    }

    private function seedPayroll(): void
    {
        $loans = app(LoansAndAdvances::class);
        $payrolls = app(Payrolls::class);
        $october = Carbon::parse('2026-10-01');
        $november = Carbon::parse('2026-11-01');
        $bank = $this->money['pkr']->id;

        $loans->postLoan($this->workflow->approve($loans->saveLoan(null, ['employee_id' => $this->employees['cutter']->id, 'loan_date' => '2026-09-20', 'amount' => '100000', 'money_account_id' => $bank, 'installment' => '10000', 'start_month' => '2026-10-01'])));
        $loans->postAdvance($this->workflow->approve($loans->saveAdvance(null, ['employee_id' => $this->employees['packer']->id, 'advance_date' => '2026-10-12', 'amount' => '15000', 'salary_month' => '2026-10-01', 'money_account_id' => $bank])));

        app(Attendance::class)->saveMonth($october, [
            $this->employees['supervisor']->id => ['working_days' => '26', 'overtime_hours' => '6'],
            $this->employees['cutter']->id => ['working_days' => '26', 'unpaid_leaves' => '1', 'late_hours' => '3'],
            $this->employees['packer']->id => ['working_days' => '26', 'paid_leaves' => '2'],
            $this->employees['storekeeper']->id => ['working_days' => '26'],
            $this->employees['clerk']->id => ['working_days' => '26', 'overtime_hours' => '4'],
        ]);

        // October is posted and paid, except the last employee so an unpaid balance shows.
        $run = $payrolls->post($this->workflow->approve($payrolls->create($october, null, null)));
        $lines = $run->lines()->orderBy('id')->get()->filter(fn (PayrollLine $l) => Decimal::compare($l->net_payable, '0') > 0)->values();
        foreach ($lines->slice(0, max($lines->count() - 1, 0)) as $line) {
            $this->pay('employee', Employee::query()->findOrFail($line->employee_id), '2026-11-02', (string) $line->net_payable, 'pkr', [['allocatable' => $line, 'amount' => (string) $line->net_payable]]);
        }

        // November is an open draft, with attendance entered and wage work waiting.
        app(WageWorks::class)->recordManual($this->employees['stitcher2'], Carbon::parse('2026-11-06'), 'Repair of returned gloves', '20', '30');
        app(Attendance::class)->saveMonth($november, [$this->employees['cutter']->id => ['working_days' => '26', 'late_hours' => '2']]);
        $payrolls->create($november, null, null);
    }
}
