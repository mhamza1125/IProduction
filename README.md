# IProduction

Manufacturing ERP: accounts, orders, purchasing, production, BOMs and inventory.
Built with Laravel 13, Livewire 4, Alpine.js and Tailwind CSS 4 on MySQL 8.

> **Status:** built phase by phase from the Manufacturing ERP Build Specification.
> Done: 1 Foundation, 2 Settings and people, 3 Catalog, 4 Inventory, 5 Finance core, 6 Purchasing, 7 Sales and planning, 8 Production, 9 Delivery and billing, 10 Remaining finance, 11 Payroll, 12 Costing and reporting, 13 Go-live (opening balances, cutover report, go-live lock, backup restore test; see `docs/GO-LIVE.md`).
> Next: 13 Go-live (opening balances, permissions review, backup test, cutover).

## What's included

| Area | Details |
|---|---|
| Sales | Customers (each in their own currency), sales orders, proforma invoices, planning (reserve stock, buy or make), deliveries with packing lists, commercial invoices with export details (HS code, origin, Incoterms, ports, BL/AWB, FI and GD numbers, REX statement), returns and credit notes. |
| Purchasing | Vendors, purchase orders, goods receipts with rejections and over-receipt tolerance, vendor bills (printable), purchase returns. |
| Production | PTCs (Product Travel Cards) following each batch through its route of stages, material issue and reconciliation, rejections with reasons, contractor jobs, material processing, wage work, and individual piece rates per worker, stage and item with effective dates. |
| Inventory | Stock ledger by item, stage, status, location and lot; FIFO lot costing; reservations; counts and adjustments; lot traceability. |
| Finance | Double-entry journals posted automatically, cash and bank accounts in any currency, receipts, payments, currency conversions (PKR typed in as received), transfers, expenses, journal vouchers, ledgers and statements, fixed assets with straight-line depreciation. |
| Payroll | Attendance, salaried and piece-rate staff, overtime, loans, advances, fines, payslips. |
| Reports | 35 reports with print, PDF, Excel and CSV: trial balance, P&L, balance sheet (current / non-current), cash flow statement, sales tax summary, receivables and payables, stock valuation, production, wastage and yield, order cost and customer profitability. |
| Control | Roles and permissions per action, approvals per document type, period lock, posted documents never edited (reversals, returns and credit notes instead), history on every document, audit log, two-factor sign-in. |
| Operations | Nightly backups with a restore test, health check at `/up` (database and storage), security headers, go-live import of opening balances, demo company for training. |
| UI | Light, Dim and Dark themes, mobile layout, keyboard shortcuts, Excel/CSV export on every list. |

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

### Demo company (optional)

To try the software with realistic data, load a sample sports-goods manufacturer after the normal seed:

```bash
php artisan db:seed --class=DemoDataSeeder
```

It adds:
- customers in USD, EUR, GBP, AED and PKR;
- vendors, contractors and employees;
- items with BOMs and routes;
- purchase orders, receipts, bills, returns and payments;
- sales orders in every state;
- running and finished PTCs, and a contractor job;
- deliveries, invoices, receipts, a return and a credit note;
- expenses, transfers and a currency conversion;
- a posted October payroll and a draft November one.

Every document is posted through the real services, so stock and the books agree.

It also adds one user per role (`manager@demo.test`, `sales@demo.test`, `purchase@demo.test`, `store@demo.test`, `production@demo.test`, `accountant@demo.test`, `hr@demo.test`), all with the password `Demo@12345`. It refuses to run in production and does nothing if the demo data is already there.

Project guide with a walkthrough of every area: open `docs/guide/index.html` in a browser.

Presenting the system to a customer: `docs/presenting/index.html` is a timed run-sheet (what to say, what to click, what to cover, with a checklist), written against the demo company.

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
   SESSION_SECURE_COOKIE=true   # the site must be served over HTTPS
   LOG_STACK=daily
   LOG_LEVEL=warning
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
