<?php

namespace App\Modules\Finance;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\Finance\Services\Ledgers;
use App\Modules\Finance\Services\MoneyAccounts;
use App\Modules\Finance\Services\Payables;
use App\Modules\Inventory\Actions\PostStockAdjustment;
use App\Modules\Inventory\Models\StockAdjustment;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Support\Decimal;
use App\Support\Modules\DocumentType;
use App\Support\Modules\ModuleServiceProvider;
use App\Support\Modules\NavItem;
use App\Support\Modules\PermissionSet;

/**
 * Finance: chart of accounts, cash and bank accounts, journals and ledgers.
 * Other modules post accounting through JournalPoster; nobody edits a balance.
 */
class FinanceServiceProvider extends ModuleServiceProvider
{
    public function name(): string
    {
        return 'Finance';
    }

    public function register(): void
    {
        parent::register();

        $this->app->singleton(JournalPoster::class);
        $this->app->singleton(Ledgers::class);
        $this->app->singleton(MoneyAccounts::class);
        $this->app->singleton(Payables::class);
    }

    public function boot(): void
    {
        parent::boot();

        // Anything with journal lines can only be deactivated.
        foreach ([Customer::class, Vendor::class, Employee::class, Contractor::class] as $class) {
            $class::whenUsed('finance.journals', fn ($p) => JournalLine::query()->where('ledgerable_type', $p->getMorphClass())->where('ledgerable_id', $p->getKey())->exists());
        }
        GlAccount::whenUsed('finance.glaccount', fn (GlAccount $a) => JournalLine::query()->where('gl_account_id', $a->id)->exists() || MoneyAccount::query()->where('gl_account_id', $a->id)->exists());
        MoneyAccount::whenUsed('finance.moneyaccount', fn (MoneyAccount $a) => JournalLine::query()->where('money_account_id', $a->id)->exists());

        // Posting map: Stock Adjustment — Dr or Cr Inventory, against Stock Adjustment Loss or gain.
        PostStockAdjustment::afterStock('finance.journal', function (StockAdjustment $adjustment, array $values) {
            $net = Decimal::sum(array_column($values, 'value'));

            if (Decimal::isZero($net)) {
                return;
            }

            $entry = new JournalEntry($adjustment->adjustment_date, 'PKR', "{$adjustment->number} stock adjustment", $adjustment);
            $amount = Decimal::round(ltrim($net, '-'));

            Decimal::isNegative($net)
                ? $entry->debit('stock_adjustment', $amount)->credit('inventory', $amount)
                : $entry->debit('inventory', $amount)->credit('stock_adjustment', $amount);

            app(JournalPoster::class)->post($entry);
        });
    }

    public function permissions(): array
    {
        return [
            new PermissionSet('chart_of_accounts', 'Chart of accounts', PermissionSet::MASTER, 'Finance'),
            new PermissionSet('accounts', 'Cash and bank accounts', [...PermissionSet::MASTER, 'print'], 'Finance'),
            new PermissionSet('ledgers', 'Ledgers', ['view', 'print', 'export'], 'Finance'),
            new PermissionSet('journals', 'Journals', ['view', 'export'], 'Finance'),
            new PermissionSet('payments', 'Payments', PermissionSet::DOCUMENT, 'Finance'),
        ];
    }

    public function documentTypes(): array
    {
        return [
            new DocumentType('journal', 'Journal (automatic)', 'JNL', approvable: false),
            new DocumentType('payment', 'Payment', 'PAY'),
        ];
    }

    public function navigation(): array
    {
        return [
            new NavItem('Accounts', 'finance.accounts.index', 'banknotes', 'accounts.view', 'Finance', 500, 'finance.accounts.*'),
            new NavItem('Payments', 'finance.payments.index', 'banknotes', 'payments.view', 'Finance', 510, 'finance.payments.*'),
            new NavItem('Ledgers', 'finance.ledgers', 'document', 'ledgers.view', 'Finance', 580),
            new NavItem('Journals', 'finance.journals.index', 'list', 'journals.view', 'Finance', 585, 'finance.journals.*'),
            new NavItem('Chart of Accounts', 'finance.chart-of-accounts', 'list', 'chart_of_accounts.view', 'Finance', 590),
        ];
    }
}
