# Go-live checklist

Use this list to move a customer from their old system to IProduction. Follow it in this order. Everything here is done in the app under **Settings > Opening Balances**, except the server steps.

## 1. Before cut-over

- [ ] **Server.**
  - Production `.env`: `APP_ENV=production`, `APP_DEBUG=false`, mail, Redis and Horizon.
  - Run `php artisan migrate --force`, `php artisan permissions:sync` and `php artisan optimize`.
- [ ] **Company settings.** Check the name, tax numbers, currencies, tolerances, numbering prefixes and approval switches.
- [ ] **Master data.**
  - Load or enter customers, vendors, contractors, employees, items with variants, stages, routes, BOMs, locations, cash and bank accounts, and GL accounts.
  - Opening files refer to these masters by their codes, SKUs and names. A row whose master record is missing is an error.
- [ ] **Users and roles.**
  - Give each person only the role they need.
  - Review the sensitive permissions: `cost.view`, `profit.view`, `salary.view`, `bank_balance.view`, every `*.override`, and `opening.*`.
  - Every screen route is protected by a permission (`tests/Feature/Foundation/GoLiveChecksTest.php` enforces this).
- [ ] **Backups.**
  - Check that the scheduler is running (`php artisan schedule:work` or cron), so `db:backup` runs daily.
  - Copy the backup folder off the server.
  - Give the database user a scratch database for restore tests:
    ```sql
    GRANT ALL PRIVILEGES ON `iproduction_restore_check`.* TO 'iprod'@'localhost';
    ```
  - Then prove a restore works:
    ```bash
    php artisan db:backup
    ```
    ```bash
    php artisan db:verify-backup
    ```
    The command restores the newest backup into the scratch database, compares the table count and the row counts of key tables, then drops the scratch database.
- [ ] **User testing.** Key users run one real order end to end in a test copy: order → PO/GRN → PTC → delivery → invoice → receipt → payroll. Fix the settings and masters the test exposes.

## 2. Cut-over date

Pick a month end, for example 31-10-2026. In the old system, stop transactions and print these closing reports, which are your **control totals**:

- the trial balance;
- cash and bank balances;
- customer, vendor, contractor and employee balances;
- stock valuation;
- the loans and advances register;
- open orders, open POs and running jobs.

## 3. Load opening documents

Download each CSV template from the Opening Balances screen. Fill it from the old system, upload it with the cut-over date, and fix every row the preview marks red. Use **Re-check** after you fix the masters. Type the old system's figure into **control totals**, then approve and post.

Load them in this order:

| # | Type | Posts |
|---|------|-------|
| 1 | Cash and banks (`cash_banks`) | Dr/Cr money account, against Opening Balance |
| 2 | Opening stock (`stock`) | Opening lots at the carried unit cost, Dr Inventory |
| 3 | Contractor stock (`contractor_stock`) | In Production at the contractor's location |
| 4 | Customer balances (`customer_balances`) | AR or customer advance, in the customer's currency |
| 5 | Vendor balances (`vendor_balances`) | AP or vendor advance |
| 6 | Contractor balances (`contractor_balances`) | Contractor payable or advance |
| 7 | Employee balances (`employee_balances`) | Wages payable or employee advance |
| 8 | Employee loans (`employee_loans`) | Loan documents; payroll recovers the remaining installments |
| 9 | Salary advances (`employee_advances`) | Advance documents; payroll recovers them |
| 10 | Open sales orders (`open_sales_orders`) | Approved orders for the remaining quantity |
| 11 | Open purchase orders (`open_pos`) | Approved POs for the quantity still to receive |
| 12 | Running PTCs (`running_ptcs`) | Jobs at their current stage, with pieces and material already issued |

Rules the importer enforces:

- The same file cannot be imported twice.
- A duplicate key within a file (for example the same customer twice) is an error.
- A batch with errors cannot be posted.
- One journal per currency is posted against the **Opening Balance** equity account.
- After all balances are loaded, Opening Balance should equal the old system's equity. If it does not, a balance is missing or a figure is wrong.
- A posted batch is fixed by cancelling it while it is unposted, or afterwards by an adjustment or journal voucher with a reason. It is never edited.

## 4. Reconcile

- [ ] Open **Reports > Go-live > Cutover**. Every type must show **Matches**. Investigate every **DIFFERENT** row, and fill in any missing control total.
- [ ] Compare these with the old system's closing reports:
  - **Trial balance** (one per currency);
  - **Stock summary and valuation**;
  - **Cash, bank and foreign balances**;
  - **Receivables and payables by party**;
  - **Loan balances**.
- [ ] Get a key user to sign the reconciliation.

## 5. Lock go-live

When everything matches, press **Lock go-live** on the Opening Balances screen and give a reason. This:

- refuses to lock while any opening batch is still draft or approved;
- blocks new opening documents for good (`opening.locked_at` is stored with the reason);
- moves the period lock to the latest opening batch date, so nothing can be posted on or before the cut-over.

## 6. First weeks

- Check the backups each morning for the first week. Run `db:verify-backup` weekly.
- Keep the old system read-only for reference. Do not run both systems in parallel.
- At the first month end, review the stock summary against a physical count, and the trial balance. `php artisan stock:rebuild-balances` recomputes stock balances from the movement ledger if they are ever in doubt.
