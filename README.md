# IProduction

Manufacturing ERP: accounts, orders, purchasing, production, BOMs and inventory.
Built with Laravel 13, Livewire 4, Alpine.js and Tailwind CSS 4 on MySQL 8.

> **Status:** built phase by phase from the Manufacturing ERP Build Specification.
> Done: 1 Foundation, 2 Settings and people, 3 Catalog, 4 Inventory, 5 Finance core, 6 Purchasing, 7 Sales and planning, 8 Production.
> Next: 9 Delivery and billing, then
> remaining Finance, Payroll, Costing and reporting, Go-live.

## What's included

| Area | Details |
|---|---|
| Sign-in | Login, password reset, two-factor authentication (authenticator app). No public registration: admins create users. Deactivated users cannot sign in. |
| Users & roles | Users screen with search/sort/filter, roles with a permission matrix per module, built-in **Super Admin** role. |
| Company settings | Name, address, NTN/STRN, logo, fiscal year start month (default July). |
| Currencies | PKR base currency plus foreign currencies. Exchange rates are entered per transaction. |
| Document numbers | Gapless, per fiscal year: `SO/2026-27/00001`. |
| Audit trail | Changes to users and currencies are logged (Spatie Activitylog). |
| UI kit | Tailwind Blade components: buttons, inputs, selects, tables with sorting, modals, toasts, dark mode, mobile sidebar. |
| Modules | `app/Modules/{Name}`: each module brings its own routes, views, migrations, permissions and menu items. |

## Requirements

- PHP 8.3 or newer (8.4 recommended) with `pdo_mysql`, `gd`, `intl`, `zip`, `mbstring`
- Composer 2, Node.js 20+
- MySQL 8.0+
- Redis (production only)

## Local setup

```bash
git clone https://github.com/mhamza1125/IProduction.git
cd IProduction
cp .env.example .env
```

Edit `.env`: set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` (create the database first, `utf8mb4`) and `ADMIN_EMAIL` / `ADMIN_PASSWORD` for the first admin account. Then:

```bash
composer setup
```

This installs dependencies, generates the app key, migrates, seeds (Super Admin, PKR and common currencies, permissions) and builds the front-end assets.

Run the app:

```bash
composer dev
```

Open http://localhost:8000 and sign in with the admin email and password from `.env`. If `ADMIN_PASSWORD` was empty, a random password is printed by the seeder.

### Tests

Tests use a separate MySQL database so they match production behaviour:

```sql
CREATE DATABASE iproduction_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```bash
composer test     # tests only
composer check    # code style + static analysis + tests (same as CI)
```

## Deploying a copy for a customer

Each customer gets their own deployment and database. Use a VPS (managed with Laravel Forge, Ploi or similar), not shared hosting: the app needs queue workers, Redis and a scheduler.

1. Create the database and a `.env` from `.env.example` with `APP_ENV=production`, `APP_DEBUG=false`, the customer's `APP_URL`, and:
   ```
   QUEUE_CONNECTION=redis
   CACHE_STORE=redis
   SESSION_DRIVER=redis
   ```
2. Deploy:
   ```bash
   composer install --no-dev --optimize-autoloader
   php artisan key:generate        # first deploy only
   php artisan migrate --force
   php artisan db:seed --force     # safe to re-run; also syncs permissions
   php artisan storage:link        # first deploy only
   npm ci && npm run build
   php artisan optimize
   ```
3. Run `php artisan horizon` under Supervisor, and add the scheduler cron entry: `* * * * * php /path/to/artisan schedule:run`.
4. Sign in as the Super Admin, change the password, turn on two-factor authentication and fill in **Company settings**.

Updates: pull, then repeat step 2 and `php artisan horizon:terminate`.

## Project structure

```
app/
  Models/User.php
  Modules/
    Core/                 users, roles, settings, currencies, document numbers (reference module)
  Support/
    Decimal.php           exact money/quantity maths
    Livewire/WithDataTable.php
    Modules/              module base class, registry, NavItem
resources/views/
  components/             Blade UI kit
  layouts/                app shell and sign-in layout
  auth/                   sign-in, password reset, 2FA pages
```

See [AGENTS.md](AGENTS.md) for coding conventions and the data rules every module must follow.
