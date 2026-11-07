<?php

namespace App\Modules\Reports\Services;

use App\Models\User;
use App\Modules\Sales\Services\Planning;
use App\Support\Decimal;
use App\Support\Format;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

/**
 * Dashboard cards (specification, Module 14). Each card links to the list or
 * report behind it and is shown only with the permission to see its data.
 */
class DashboardData
{
    /**
     * @return list<array{title: string, value: string, detail?: string|null, url: string|null, rows?: list<array{0: string, 1: string}>}>
     */
    public function for(?User $user): array
    {
        if (! $user) {
            return [];
        }
        $cards = [];
        $today = now()->toDateString();
        $open = ['approved', 'in_planning', 'in_progress', 'partly_delivered'];

        if ($user->can('sales_orders.view')) {
            $count = DB::table('sales_orders')->whereIn('status', $open)->count();
            $late = DB::table('sales_orders')->whereIn('status', $open)->where('due_date', '<', $today)->count();
            $cards[] = ['title' => 'Open orders', 'value' => (string) $count, 'detail' => null, 'url' => route('sales.orders.index')];
            $cards[] = ['title' => 'Late orders', 'value' => (string) $late, 'detail' => 'Past due date and not delivered', 'url' => route('sales.orders.index', ['late' => 1]), 'alert' => $late > 0];
        }
        if ($user->can('planning.view')) {
            $short = app(Planning::class)->openLines()->filter(fn ($r) => Decimal::compare($r['shortfall'], '0') > 0)->count();
            $cards[] = ['title' => 'Stock shortages', 'value' => (string) $short, 'detail' => 'Open order lines not covered by stock or supply', 'url' => route('sales.planning'), 'alert' => $short > 0];
        }
        if ($user->can('ptcs.view')) {
            $rows = DB::table('stock_balances as b')->join('stages as s', 's.id', '=', 'b.stage_id')->whereNotNull('b.stage_id')->whereIn('b.status', ['available', 'in_production'])
                ->where('b.quantity', '>', 0)->groupBy('s.name', 's.sort_order')->orderBy('s.sort_order')->get(['s.name', DB::raw('SUM(b.quantity) as q')]);
            $cards[] = ['title' => 'Production by stage', 'value' => Format::qty(Decimal::sum($rows->pluck('q')->map(fn ($v) => (string) $v), 4)), 'detail' => 'Pieces at a stage',
                'url' => route('reports.show', 'stage-stock'), 'rows' => $rows->map(fn ($r) => [$r->name, Format::qty((string) $r->q)])->all()];
        }
        if ($user->can('stock.view')) {
            $rejected = DB::table('stock_balances')->where('status', 'rejected')->sum('quantity');
            $cards[] = ['title' => 'Rejected stock', 'value' => Format::qty((string) $rejected), 'detail' => DB::table('rejections')->where('status', 'open')->count().' rejections waiting for a decision',
                'url' => $user->can('rejections.view') ? route('production.rejections.index') : route('reports.show', 'stock-summary')];
        }
        $pending = $this->pendingApprovals($user);
        if ($pending !== []) {
            $cards[] = ['title' => 'Pending approvals', 'value' => (string) array_sum(array_column($pending, 1)), 'detail' => null, 'url' => null,
                'rows' => array_map(fn ($p) => [$p[0], (string) $p[1]], $pending), 'links' => array_column($pending, 2)];
        }
        if ($user->can('bank_balance.view') && $user->can('accounts.view')) {
            $balances = DB::table('journal_lines as l')->join('money_accounts as m', 'm.id', '=', 'l.money_account_id')->groupBy('m.currency_code')
                ->get(['m.currency_code', DB::raw('SUM(l.debit) - SUM(l.credit) as b')]);
            $pkr = (string) ($balances->firstWhere('currency_code', 'PKR')->b ?? '0');
            $cards[] = ['title' => 'Cash and bank (PKR)', 'value' => Format::money($pkr, 'PKR'), 'detail' => null, 'url' => route('reports.show', 'money-balances')];
            $foreign = $balances->where('currency_code', '!=', 'PKR');
            if ($foreign->isNotEmpty()) {
                $cards[] = ['title' => 'Foreign balances', 'value' => (string) $foreign->count().' currencies', 'detail' => null, 'url' => route('reports.show', 'money-balances'),
                    'rows' => $foreign->map(fn ($r) => [$r->currency_code, Format::money((string) $r->b)])->values()->all()];
            }
        }
        if ($user->can('ledgers.view')) {
            $ar = DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->where('a.system_key', 'accounts_receivable')->groupBy('l.currency_code')
                ->get(['l.currency_code', DB::raw('SUM(l.debit) - SUM(l.credit) as b')])->filter(fn ($r) => ! Decimal::isZero((string) $r->b));
            $cards[] = ['title' => 'Receivables', 'value' => $ar->isEmpty() ? '0' : $ar->count().' '.str('currency')->plural($ar->count()), 'detail' => 'Owed by customers, per currency', 'url' => route('reports.show', 'receivables'),
                'rows' => $ar->map(fn ($r) => [$r->currency_code, Format::money((string) $r->b)])->values()->all()];
            $ap = DB::table('journal_lines as l')->join('gl_accounts as a', 'a.id', '=', 'l.gl_account_id')->whereIn('a.system_key', ['accounts_payable', 'contractor_payable', 'wages_payable'])
                ->where('l.currency_code', 'PKR')->groupBy('a.name')->get(['a.name', DB::raw('SUM(l.credit) - SUM(l.debit) as b')]);
            $cards[] = ['title' => 'Payables (PKR)', 'value' => Format::money(Decimal::sum($ap->pluck('b')->map(fn ($v) => (string) $v)), 'PKR'), 'detail' => null, 'url' => route('reports.show', 'party-balances'),
                'rows' => $ap->map(fn ($r) => [$r->name, Format::money((string) $r->b)])->all()];
        }
        if ($user->can('deliveries.view')) {
            $month = DB::table('deliveries as d')->join('delivery_lines as l', 'l.delivery_id', '=', 'd.id')->where('d.status', 'dispatched')
                ->whereBetween('d.delivery_date', [now()->startOfMonth()->toDateString(), $today]);
            $cards[] = ['title' => "This month's deliveries", 'value' => (string) (clone $month)->distinct()->count('d.id'), 'detail' => Format::qty((string) (clone $month)->sum('l.quantity')).' pieces',
                'url' => route('delivery.deliveries.index', ['status' => 'dispatched'])];
        }
        if ($user->can('invoices.view')) {
            $top = DB::table('invoices as i')->join('customers as c', 'c.id', '=', 'i.customer_id')->whereNotNull('i.posted_at')->where('i.invoice_date', '>=', now()->startOfYear()->toDateString())
                ->groupBy('c.name', 'i.currency_code')->orderByDesc(DB::raw('SUM(i.total)'))->limit(5)->get(['c.name', 'i.currency_code', DB::raw('SUM(i.total) as t')]);
            $cards[] = ['title' => 'Top customers this year', 'value' => (string) $top->count(), 'detail' => null, 'url' => route('reports.show', 'customer-sales'),
                'rows' => $top->map(fn ($r) => [$r->name, Format::money((string) $r->t, $r->currency_code)])->all()];
        }

        return $cards;
    }

    /** @return list<array{0: string, 1: int, 2: string}> label, count, url */
    private function pendingApprovals(User $user): array
    {
        $types = [
            ['sales_orders', 'sales_orders', 'Sales orders', 'sales.orders.index'], ['purchase_orders', 'purchase_orders', 'Purchase orders', 'purchasing.orders.index'],
            ['goods_receipts', 'goods_receipts', 'Goods receipts', 'purchasing.receipts.index'], ['vendor_bills', 'vendor_bills', 'Vendor bills', 'purchasing.bills.index'],
            ['ptcs', 'ptcs', 'PTCs', 'production.ptcs.index'], ['contractor_jobs', 'contractor_jobs', 'Contractor jobs', 'production.jobs.index'],
            ['invoices', 'invoices', 'Invoices', 'delivery.invoices.index'], ['payments', 'payments', 'Payments', 'finance.payments.index'],
            ['receipts', 'receipts', 'Customer receipts', 'finance.receipts.index'], ['journal_vouchers', 'journal_vouchers', 'Journal vouchers', 'finance.vouchers.index'],
            ['payroll_runs', 'payroll', 'Payroll', 'payroll.runs.index'], ['stock_adjustments', 'stock_adjustments', 'Stock adjustments', 'inventory.adjustments.index'],
        ];
        $out = [];
        foreach ($types as [$table, $perm, $label, $route]) {
            if (! $user->can("{$perm}.approve") || ! Route::has($route)) {
                continue;
            }
            $n = DB::table($table)->where('status', 'draft')->count();
            if ($n > 0) {
                $out[] = [$label, $n, route($route, ['status' => 'draft'])];
            }
        }

        return $out;
    }
}
