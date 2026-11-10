<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The kinds of things that have a ledger, and the permission needed to see each.
 */
final class LedgerSubjects
{
    /** type => [label, model class, permission to view] */
    public const TYPES = [
        'customer' => ['Customer', Customer::class, 'customers.view'],
        'vendor' => ['Vendor', Vendor::class, 'vendors.view'],
        'employee' => ['Employee', Employee::class, 'employees.view'],
        'contractor' => ['Contractor', Contractor::class, 'contractors.view'],
        'account' => ['Cash / bank account', MoneyAccount::class, 'bank_balance.view'],
        'gl' => ['Chart of accounts', GlAccount::class, 'chart_of_accounts.view'],
    ];

    /**
     * "CUS-0002 Rheinland GmbH", or just the name for subjects without a code
     * (cash and bank accounts). Reads the raw attributes, so a model without a
     * code column never trips strict mode.
     */
    public static function label(Model $subject): string
    {
        $attributes = $subject->getAttributes();

        return trim(($attributes['code'] ?? '').' '.($attributes['name'] ?? ''));
    }

    public static function find(string $type, int $id): ?Model
    {
        $class = self::TYPES[$type][1] ?? null;

        return $class ? $class::query()->find($id) : null;
    }

    public static function currency(string $type, Model $subject, string $glCurrency = 'PKR'): string
    {
        return match ($type) {
            'customer', 'account' => (string) $subject->getAttribute('currency_code'),
            'gl' => $glCurrency,
            default => 'PKR',
        };
    }

    /**
     * @return array{opening: string, rows: Collection<int, array{line: JournalLine, balance: string}>, closing: string, debits: string, credits: string}
     */
    public static function ledger(string $type, Model $subject, ?CarbonInterface $from, ?CarbonInterface $to, string $glCurrency = 'PKR'): array
    {
        $ledgers = app(Ledgers::class);

        return match (true) {
            $subject instanceof MoneyAccount => $ledgers->moneyLedger($subject, $from, $to),
            $subject instanceof GlAccount => $ledgers->accountLedger($subject, $glCurrency, $from, $to),
            default => $ledgers->partyLedger($subject, $from, $to),
        };
    }
}
