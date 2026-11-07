# IProduction: project conventions

Manufacturing ERP for one company per deployment (each customer gets their own copy and database).
Base currency is PKR; foreign-currency documents store the rate entered by the user.

## Stack

- PHP 8.3+ (8.4 in production), Laravel 13, MySQL 8
- Livewire 4 (class-based components) + Alpine.js (bundled with Livewire) + Tailwind CSS 4, built with Vite
- Fortify (login, password reset, 2FA; registration is off), Spatie Permission, Spatie Activitylog
- brick/math via `App\Support\Decimal` for every money/quantity calculation
- Horizon + Redis for queues in production; the database driver locally
- Pest 4, Larastan (level 6), Pint

No admin-panel framework (no Filament/Nova). UI is our own Blade components in `resources/views/components`.

## Commands

```bash
composer dev        # server, queue, logs and Vite together
composer test       # Pest (needs the MySQL database iproduction_test)
composer check      # Pint --test + Larastan + tests (what CI runs)
composer lint       # fix code style
php artisan permissions:sync   # after adding permissions to a module
```

## Modules

Every business area is a module in `app/Modules/{Name}`:

```
app/Modules/Inventory/
  InventoryServiceProvider.php   extends App\Support\Modules\ModuleServiceProvider
  Models/  Services/  Livewire/  Actions/  Enums/
  Database/Migrations/           loaded automatically
  Database/Factories/  Database/Seeders/
  resources/views/               view namespace "inventory::"
  resources/views/livewire/      views of the module's Livewire components
  routes/web.php                 loaded in the "web" middleware group
```

- Register the provider in `bootstrap/providers.php` and its seeder in `DatabaseSeeder`.
- Declare permissions in `permissions()` as `PermissionSet` objects: names are `{resource}.{action}` (e.g. `sales_orders.approve`). Actions: view, create, edit, delete, approve, post, cancel, print, export, override. Sensitive data has its own: `cost.view`, `profit.view`, `salary.view`, `bank_balance.view`. Run `php artisan permissions:sync` after changes; it also grants new permissions to the default roles in `config/roles.php`.
- Declare document types in `documentTypes()` (key, label, number prefix); they appear in Settings > Numbering and Approvals.
- Declare sidebar links in `navigation()` with `NavItem`; links are hidden from users without the permission.
- Protect routes with `->middleware('can:...')` **and** call `$this->authorize()` in every Livewire action that changes data.
- Modules talk to each other through service classes or events, never by writing to another module's tables directly.
- `app/Modules/Core` is the reference implementation: copy its patterns.

## Data rules (non-negotiable)

- **Money and quantities:** `DECIMAL` columns (money `decimal(18,2)`, quantity `decimal(18,4)`, unit cost and unit price `decimal(18,4)`; specification: money 2, quantity 4, unit cost 4 decimals). Do the maths with `App\Support\Decimal`. Never use PHP floats.
- **Foreign currency:** customer documents, ledgers and receipts stay in the customer's currency; no exchange rate is used. Only a Currency Conversion creates PKR value (PKR received is typed in). Cost entries are always PKR.
- **Document numbers** (`SO-26-0001`) come from `App\Modules\Core\Services\DocumentNumber::next($type, $date)` inside the same `DB::transaction()` that creates the document. Never derive them from auto-increment IDs.
- **Document lifecycle:** Draft → Approved → Posted, or Cancelled while nothing is posted. Document models implement `App\Support\Documents\Document`, use `HasDocumentWorkflow`, and add columns with `$table->documentWorkflow()`. Anything after Draft is locked by the model itself.
- **Posting** goes through `App\Support\Documents\DocumentWorkflow::post($doc, fn ($locked) => ...effects...)`: one transaction, document row locked, double-post safe, approval setting and period lock enforced. Business actions live in action classes (`PostGoodsReceipt`) that call it; Livewire components stay thin.
- Posted mistakes are fixed with a reversal, return, credit note or adjustment, never an update or delete. Cancel, override, reversal and adjustment always need a reason (logged).
- **Stock is a ledger:** only `App\Modules\Inventory\Services\StockLedger` writes stock. Every change is an append-only `stock_movements` row plus the matching `stock_balances` update in the same transaction (`php artisan stock:rebuild-balances` recomputes balances). Use `createLot()` for every inbound source, `receive()`, `remove()`, `transfer()` (status/stage/location change keeping lot and cost), `allocate()/issue()` (picked lot, else the demand's reserved lots, else FIFO; never another demand's reservation), `reserve()/release()/reallocate()`. Shortages throw `DocumentException`, so the whole document rolls back. Statuses: available, in_production, rejected, returned; only available stock can be issued or reserved.
- **Accounting is double-entry:** only `App\Modules\Finance\Services\JournalPoster` writes journals: build a `JournalEntry` (one currency, accounts by system key such as `accounts_receivable`, a `MoneyAccount`, or a `GlAccount`; tag party lines with the customer/vendor/employee/contractor as `ledgerable`) and `post()` it inside the document's posting transaction. It must balance; journals are immutable; `reverse()` posts the mirror. Ledgers and balances come from `Ledgers` (never stored). Follow the specification's posting map.
- Supply for a sales order line is linked with `SupplyAllocation` (PO line or, later, production); planning counts open allocations as incoming, never as stock. Finished goods received against an allocation are reserved to the order line automatically.
- **Production:** stage work goes through `PtcTransactions` (material/extra issue, stage issue/receive, reject, return, wastage, release), lifecycle through `Ptcs` (approve copies route + BOM and creates the output lot; child, split, merge, complete, close early). PTC pieces live in the PTC's output lot by stage. Costs collect as signed `ptc_costs` rows (`PtcCosting`); a release moves the cost share onto the output lot, rejected pieces are allowed loss. Journals for wages, contractors, wastage/scrap and recoveries come from `ProductionAccounting`.
- **Delivery and billing:** `Deliveries` (save → allocate reserves to the order line → `Packing` complete → dispatch posts COGS), `Invoices` (dispatched lines once; optional tax per line; Dr AR / Cr Sales + Taxes Payable in customer currency; `allocate()` sets credit notes and later receipts against an invoice), `DeliveryReturns` (Returned status, then restock or send to Rejections), `CreditNotes`. Order progress (delivered, invoiced, returned) is updated only through `Sales\Services\OrderProgress`.
- **Receipts and money movements:** `Receipts` (customer currency only; amount cleared = deductions + net; allocations go to `Receivable` documents registered with `Receivables::register()`, the rest is a customer advance applied later with `applyAdvance()`), `MoneyMovements` (currency conversion with typed-in PKR, same-currency transfers, expenses paid now or payable to a vendor, balanced journal vouchers with optional payroll deductions).
- **Payroll:** `Payrolls` builds a draft run per month (salary: salary + overtime − unpaid days − late hours; wage: approved unpaid wage work from `WageWorks::unpaidFor()`; both: allowances, fines from `MoneyMovements::payrollFines()`, loan installments, advances, amount brought forward; a negative net carries forward). Nothing changes until posting; `posted_key` makes one posted payroll per employee and month. Payroll lines are `Payable`, so payments settle them. Rates and switches come from `payroll.*` settings (overtime multiplier, salaried only).
- **Reports:** a report is a class in `app/Modules/Reports/Definitions/{Area}` extending `Reports\Support\Report` (key, title, group, extra `permissions()` such as `cost.view`, `filters()`, `columns()`, `build()` returning rows). Classes there are registered automatically and get screen, print, Excel/CSV export and permission checks from the shared screen. Reports read posted data only. Order cost comes from `OrderCosting` (direct cost only; profit uses a reference PKR value and posts nothing).
- Hooks between modules (e.g. `PostStockAdjustment::afterStock('finance.journal', ...)`) and usage checks (`Model::whenUsed('module.key', ...)`) are keyed so repeated boots don't register twice.
- Lock rows you are about to change based on their current value (`lockForUpdate()` inside a transaction): stock balances, sequences, credit limits.
- Add `LogsActivity` to models whose changes people will ask about later.
- Company-specific values (name, tax numbers, tolerances, lock date) come from `App\Modules\Core\Services\Settings`, not config or constants.
- Unknown or "none" values are NULL, never 0. A master record used in a transaction can only be deactivated, not deleted. Inactive records cannot be picked in new documents.

## UI conventions

- Full-page Livewire components are routed directly (`Route::get('items', Items\Index::class)`) and use the `layouts::app` layout. Set the page title with `#[Title('...')]` or `->title()`.
- List screens use the `App\Support\Livewire\WithDataTable` trait plus `<x-table>`, `<x-table.toolbar>`, `<x-table.th sortable=...>`.
- Forms use `<x-input>`, `<x-select>`, `<x-textarea>`, `<x-checkbox>`, `<x-date-input>`: errors are shown automatically from `wire:model`. Add `data-save-shortcut` to the main `<form>` so Ctrl+S saves.
- Dates display as dd-mm-yyyy (`@date($d)`, `<x-date-input>`); money with `@money($amount, $currency)`; quantities with `@qty($q)`. Status badges: `<x-status :status="...">`.
- Every list: search, filters, date range (`dates` on the toolbar + `applyDateRange()`), sorting, pagination, and Excel/CSV export (`use WithExport` + `exportPermission/exportQuery/exportColumns`).
- Every document/profile view shows attachments, notes and history: `<livewire:core::shared.attachments :model="$doc" />`, `...shared.notes`, `...shared.activity-history` (the model implements `Attachable`).
- Printouts: a controller checks `{resource}.print`, then `Printer::respond($request, 'module::print.x', [...], $title)` with a view built on `<x-print.layout>`. `?pdf=1` downloads a PDF.
- Use `wire:model` (deferred) by default. Only search boxes use `.live` (debounced).
- Long line-item grids where speed matters (sales/purchase order lines) run in Alpine on the client and are sent to the server once on save. Short setup grids (route stages, BOM lines) may use Livewire arrays with deferred `wire:model`.
- Never name a view variable `$attributes`: Blade reserves it (use `$attributeList`).
- Notifications: `$this->dispatch('toast', message: '...')`, or `session()->flash('toast', '...')` before a redirect.
- Mark properties the browser must not change with `#[Locked]` (IDs, file paths, the model being edited).
- Keep component state small: store IDs, use computed properties for collections.

## Testing

- Feature tests run on MySQL (`iproduction_test`) with `RefreshDatabase`, not SQLite, so decimals, locking and unique indexes behave as in production.
- Helpers in `tests/Pest.php`: `seedBase()` (settings, chart of accounts, Cash Book, locations), `admin()`, `userWith([...permissions])`, `markUsedWhen(Model::class, fn)`. `tests/Fixtures` has a sample `TestDocument` for framework tests.
- `TestCase` refuses to run unless the database name ends in `_test`; destructive artisan commands are blocked in production.
- If a test run stops silently, run `PAO_DISABLE=1 vendor/bin/pest` to see the real error (laravel/pao reformats output for AI agents and can hide fatal errors).
- Every module ships tests for its permissions, its validation and its posting rules.
