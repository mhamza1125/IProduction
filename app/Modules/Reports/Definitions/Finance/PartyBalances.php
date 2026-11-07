<?php

namespace App\Modules\Reports\Definitions\Finance;

use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Customer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Filter;
use App\Modules\Reports\Support\Report;
use App\Support\Decimal;
use Illuminate\Support\Facades\DB;

class PartyBalances extends Report
{
    public function key(): string
    {
        return 'party-balances';
    }

    public function title(): string
    {
        return 'Receivables and payables by party';
    }

    public function group(): string
    {
        return 'Finance';
    }

    public function description(): string
    {
        return 'Balance of every customer, vendor, contractor and employee, in their own currency.';
    }

    public function permissions(): array
    {
        return ['ledgers.view'];
    }

    public function filters(): array
    {
        return [new Filter('kind', 'Party type', options: ['customer' => 'Customers', 'vendor' => 'Vendors', 'contractor' => 'Contractors', 'employee' => 'Employees'])];
    }

    public function columns(): array
    {
        return [
            new Column('kind', 'Type'), new Column('code', 'Code'), new Column('party', 'Party'), new Column('balance', 'Balance (+ owes us / − we owe)', 'money', 'currency', total: true), new Column('currency', 'Currency'),
        ];
    }

    protected function build(array $filters): iterable
    {
        $types = ['customer' => Customer::class, 'vendor' => Vendor::class,
            'contractor' => Contractor::class, 'employee' => Employee::class];
        $rows = collect();
        foreach ($types as $kind => $class) {
            if (! empty($filters['kind']) && $filters['kind'] !== $kind) {
                continue;
            }
            $table = (new $class)->getTable();
            DB::table('journal_lines as l')->join($table.' as p', 'p.id', '=', 'l.ledgerable_id')->where('l.ledgerable_type', (new $class)->getMorphClass())
                ->groupBy('p.code', 'p.name', 'l.currency_code')->orderBy('p.name')
                ->get(['p.code', 'p.name', 'l.currency_code', DB::raw('SUM(l.debit) - SUM(l.credit) as balance')])
                ->filter(fn ($r) => ! Decimal::isZero((string) $r->balance))
                ->each(fn ($r) => $rows->push(['kind' => ucfirst($kind), 'code' => $r->code, 'party' => $r->name, 'balance' => Decimal::round((string) $r->balance), 'currency' => $r->currency_code]));
        }

        return $rows;
    }
}
