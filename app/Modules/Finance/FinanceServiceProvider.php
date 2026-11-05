<?php

namespace App\Modules\Finance;

use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Finance: chart of accounts, journals, ledgers, cash and bank accounts,
 * payments, receipts, transfers, conversions, expenses and vouchers.
 */
class FinanceServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Finance';
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('chart_of_accounts', 'Chart of accounts', PermissionSet::MASTER, 'Finance'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Chart of Accounts', 'finance.chart-of-accounts', 'list', 'chart_of_accounts.view', 'Finance', 590),
        ];
    }
}
