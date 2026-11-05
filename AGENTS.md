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
- Declare permissions in `permissions()` as `{module}.{resource}.{action}` (e.g. `inventory.items.manage`), then run `php artisan permissions:sync`.
- Declare sidebar links in `navigation()` with `NavItem`; links are hidden from users without the permission.
- Protect routes with `->middleware('can:...')` **and** call `$this->authorize()` in every Livewire action that changes data.
- Modules talk to each other through service classes or events, never by writing to another module's tables directly.
- `app/Modules/Core` is the reference implementation: copy its patterns.

## Data rules (non-negotiable)

- **Money and quantities:** `DECIMAL` columns (money `decimal(18,2)`, quantity `decimal(18,4)`, unit cost / exchange rate `decimal(20,6)`). Do the maths with `App\Support\Decimal`. Never use PHP floats.
- **Foreign currency documents** store `currency_code`, `exchange_rate` (entered by the user) and the PKR amounts alongside the foreign amounts.
- **Document numbers** come from `App\Modules\Core\Services\DocumentNumber::next()` inside the same `DB::transaction()` that saves the document. Never derive them from auto-increment IDs.
- **Posted documents are immutable.** Draft → Confirmed → Posted → Cancelled; correct a posted document with a reversal, never an update or delete.
- **Stock is a ledger:** every movement is an insert into a movements table; balances are derived (or cached in the same transaction).
- **Accounting is double-entry:** journal lines must balance (debits = credits) before commit.
- Lock rows you are about to change based on their current value (`lockForUpdate()` inside a transaction): stock balances, sequences, credit limits.
- Add `LogsActivity` to models whose changes people will ask about later.
- Company-specific values (name, tax numbers, fiscal year) come from `App\Modules\Core\Services\Settings`, not config or constants.

## UI conventions

- Full-page Livewire components are routed directly (`Route::get('items', Items\Index::class)`) and use the `layouts::app` layout. Set the page title with `#[Title('...')]` or `->title()`.
- List screens use the `App\Support\Livewire\WithDataTable` trait plus `<x-table>`, `<x-table.toolbar>`, `<x-table.th sortable=...>`.
- Forms use `<x-input>`, `<x-select>`, `<x-textarea>`, `<x-checkbox>`: errors are shown automatically from `wire:model`. Add `data-save-shortcut` to the main `<form>` so Ctrl+S saves.
- Use `wire:model` (deferred) by default. Only search boxes use `.live` (debounced).
- Line-item grids (order lines, BOM lines) run in Alpine on the client and are sent to the server once on save.
- Notifications: `$this->dispatch('toast', message: '...')`, or `session()->flash('toast', '...')` before a redirect.
- Mark properties the browser must not change with `#[Locked]` (IDs, file paths, the model being edited).
- Keep component state small: store IDs, use computed properties for collections.

## Testing

- Feature tests run on MySQL (`iproduction_test`) with `RefreshDatabase`, not SQLite, so decimals, locking and unique indexes behave as in production.
- Helpers in `tests/Pest.php`: `superAdmin()`, `userWith([...permissions])`.
- Every module ships tests for its permissions, its validation and its posting rules.
