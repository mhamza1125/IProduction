<?php

namespace App\Modules\Finance\Database\Seeders;

use App\Modules\Finance\Models\GlAccount;
use Illuminate\Database\Seeder;

/**
 * The default chart of accounts from the specification. Every account here
 * has a system_key because posting rules refer to it. Safe to re-run.
 *
 * The company's accountant should review this before go-live.
 */
class ChartOfAccountsSeeder extends Seeder
{
    /** system_key => [code, name, type] */
    public const ACCOUNTS = [
        // Assets
        'cash' => ['1100', 'Cash', 'asset'],
        'banks' => ['1200', 'Banks', 'asset'],
        'foreign_currency_accounts' => ['1250', 'Foreign Currency Accounts', 'asset'],
        'accounts_receivable' => ['1300', 'Accounts Receivable', 'asset'],
        'inventory' => ['1400', 'Inventory', 'asset'],
        'employee_advances' => ['1510', 'Employee Advances', 'asset'],
        'employee_loans' => ['1520', 'Employee Loans', 'asset'],
        'vendor_advances' => ['1530', 'Vendor Advances', 'asset'],
        'contractor_advances' => ['1540', 'Contractor Advances', 'asset'],
        // Liabilities
        'accounts_payable' => ['2100', 'Accounts Payable', 'liability'],
        'contractor_payable' => ['2200', 'Contractor Payable', 'liability'],
        'wages_payable' => ['2300', 'Wages and Salaries Payable', 'liability'],
        'customer_advances' => ['2400', 'Customer Advances', 'liability'],
        'taxes_payable' => ['2500', 'Taxes Payable', 'liability'],
        // Equity
        'owner_capital' => ['3100', 'Owner Capital', 'equity'],
        'retained_earnings' => ['3200', 'Retained Earnings', 'equity'],
        'opening_balance' => ['3300', 'Opening Balance', 'equity'],
        // Income
        'sales' => ['4100', 'Sales', 'income'],
        'sales_rejected' => ['4200', 'Sales of Rejected Goods', 'income'],
        'foreign_receipts_realized' => ['4300', 'Foreign Receipts Realized', 'income'],
        'damage_recoveries' => ['4400', 'Damage Recoveries', 'income'],
        // Cost
        'cogs' => ['5100', 'Cost of Goods Sold', 'cost'],
        'production_loss' => ['5200', 'Production Loss', 'cost'],
        'stock_adjustment' => ['5300', 'Stock Adjustment Loss', 'cost'],
        // Expenses
        'salaries' => ['6100', 'Salaries', 'expense'],
        'bank_charges' => ['6200', 'Bank Charges', 'expense'],
        'tax_deducted' => ['6300', 'Tax Deducted', 'expense'],
        'rent' => ['6400', 'Rent', 'expense'],
        'utilities' => ['6500', 'Utilities', 'expense'],
        'freight' => ['6600', 'Freight', 'expense'],
        'other_expenses' => ['6900', 'Other Expenses', 'expense'],
        // Clearing
        'currency_exchange_clearing' => ['9100', 'Currency Exchange Clearing', 'clearing'],
    ];

    public function run(): void
    {
        foreach (self::ACCOUNTS as $key => [$code, $name, $type]) {
            $account = GlAccount::query()->withTrashed()->where('system_key', $key)->first();

            if ($account === null) {
                $account = new GlAccount(['code' => $code, 'name' => $name, 'type' => $type]);
                $account->system_key = $key;
                $account->save();
            }
        }
    }
}
