<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\CurrencyConversion;
use App\Modules\Finance\Models\Expense;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalVoucher;
use App\Modules\Finance\Models\JournalVoucherLine;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Transfer;
use App\Modules\People\Models\Employee;
use App\Modules\People\Models\Vendor;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Currency conversions, transfers, expenses and journal vouchers
 * (specification, Module 10 and the posting map).
 */
class MoneyMovements
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly MoneyAccounts $accounts,
    ) {}

    // ---- Currency conversion ------------------------------------------------

    /** @param  array<string, mixed>  $data */
    public function saveConversion(?CurrencyConversion $doc, array $data): CurrencyConversion
    {
        $this->assertDraft($doc);
        $from = MoneyAccount::query()->findOrFail($data['from_account_id']);
        $to = MoneyAccount::query()->findOrFail($data['to_account_id']);
        if ($from->currency_code === 'PKR') {
            throw new DocumentException('Convert from a foreign-currency account.');
        }
        if ($to->currency_code !== 'PKR') {
            throw new DocumentException('Convert into a PKR account.');
        }
        $this->assertPositive((string) $data['from_amount'], 'The amount converted');
        $this->assertPositive((string) $data['pkr_received'], 'The PKR received');
        $deductions = ($data['deductions'] ?? '') === '' || $data['deductions'] === null ? null : Decimal::round((string) $data['deductions']);
        if ($deductions !== null && Decimal::isNegative($deductions)) {
            throw new DocumentException('Deductions cannot be negative.');
        }

        return DB::transaction(function () use ($doc, $data, $deductions) {
            $doc ??= new CurrencyConversion(['number' => $this->numbers->next('currency_conversion', Carbon::parse($data['conversion_date'])), 'created_by' => auth()->id()]);
            $doc->fill([
                'conversion_date' => $data['conversion_date'], 'from_account_id' => $data['from_account_id'], 'from_amount' => Decimal::round((string) $data['from_amount']),
                'to_account_id' => $data['to_account_id'], 'pkr_received' => Decimal::round((string) $data['pkr_received']), 'deductions' => $deductions,
                'deduction_account_id' => $deductions !== null && Decimal::compare($deductions, '0') > 0 ? ($data['deduction_account_id'] ?? null ?: GlAccount::system('bank_charges')->id) : null,
                'reference' => $data['reference'] ?? null ?: null, 'notes' => $data['notes'] ?? null ?: null,
            ])->save();

            return $doc;
        });
    }

    /**
     * Foreign side: Dr Currency Exchange Clearing, Cr foreign account (foreign currency).
     * PKR side: Dr PKR account (received), Dr deduction head, Cr Foreign Receipts Realized.
     * The customer ledger is not touched.
     */
    public function postConversion(CurrencyConversion $doc, ?string $overrideReason = null): CurrencyConversion
    {
        return $this->workflow->post($doc, function (CurrencyConversion $c) {
            $from = $c->fromAccount()->firstOrFail();
            $this->accounts->assertCanPay($from, $c->from_amount);
            $this->journals->post((new JournalEntry($c->conversion_date, $from->currency_code, "{$c->number} {$from->currency_code} {$c->from_amount} converted", $c))
                ->debit('currency_exchange_clearing', $c->from_amount)->credit($from, $c->from_amount));

            $realized = Decimal::add($c->pkr_received, $c->deductions ?? '0');
            $entry = (new JournalEntry($c->conversion_date, 'PKR', "{$c->number} PKR realized from {$from->currency_code} {$c->from_amount}", $c))
                ->debit($c->toAccount()->firstOrFail(), $c->pkr_received);
            if (Decimal::compare($c->deductions ?? '0', '0') > 0) {
                $entry->debit($c->deductionAccount()->firstOrFail(), (string) $c->deductions, null, 'Conversion charges');
            }
            $this->journals->post($entry->credit('foreign_receipts_realized', $realized));
        }, $overrideReason);
    }

    // ---- Transfer ---------------------------------------------------------

    /** @param  array<string, mixed>  $data */
    public function saveTransfer(?Transfer $doc, array $data): Transfer
    {
        $this->assertDraft($doc);
        $from = MoneyAccount::query()->findOrFail($data['from_account_id']);
        $to = MoneyAccount::query()->findOrFail($data['to_account_id']);
        if ($from->id === $to->id) {
            throw new DocumentException('Choose two different accounts.');
        }
        if ($from->currency_code !== $to->currency_code) {
            throw new DocumentException('Different currencies: use a Currency Conversion.');
        }
        $this->assertPositive((string) $data['amount'], 'The amount');

        return DB::transaction(function () use ($doc, $data) {
            $doc ??= new Transfer(['number' => $this->numbers->next('transfer', Carbon::parse($data['transfer_date'])), 'created_by' => auth()->id()]);
            $doc->fill(['transfer_date' => $data['transfer_date'], 'from_account_id' => $data['from_account_id'], 'to_account_id' => $data['to_account_id'],
                'amount' => Decimal::round((string) $data['amount']), 'reference' => $data['reference'] ?? null ?: null, 'notes' => $data['notes'] ?? null ?: null])->save();

            return $doc;
        });
    }

    /** Dr destination account, Cr source account. */
    public function postTransfer(Transfer $doc, ?string $overrideReason = null): Transfer
    {
        return $this->workflow->post($doc, function (Transfer $t) {
            $from = $t->fromAccount()->firstOrFail();
            $this->accounts->assertCanPay($from, $t->amount);
            $this->journals->post((new JournalEntry($t->transfer_date, $from->currency_code, "{$t->number} transfer {$from->name} → {$t->toAccount()->firstOrFail()->name}", $t))
                ->debit($t->toAccount()->firstOrFail(), $t->amount)->credit($from, $t->amount));
        }, $overrideReason);
    }

    // ---- Expense ----------------------------------------------------------

    /** @param  array<string, mixed>  $data */
    public function saveExpense(?Expense $doc, array $data): Expense
    {
        $this->assertDraft($doc);
        $head = GlAccount::query()->findOrFail($data['expense_account_id']);
        if (! in_array($head->type, ['expense', 'cost'], true)) {
            throw new DocumentException("{$head->name} is not an expense head.");
        }
        $paidFrom = $data['money_account_id'] ?? null ?: null;
        $vendor = $data['vendor_id'] ?? null ?: null;
        if ($paidFrom === null && $vendor === null) {
            throw new DocumentException('Choose the account it is paid from, or the vendor it is payable to.');
        }
        if ($paidFrom !== null && MoneyAccount::query()->whereKey($paidFrom)->value('currency_code') !== 'PKR') {
            throw new DocumentException('Expenses are paid from PKR accounts.');
        }
        $this->assertPositive((string) $data['amount'], 'The amount');

        return DB::transaction(function () use ($doc, $data, $paidFrom, $vendor) {
            $doc ??= new Expense(['number' => $this->numbers->next('expense', Carbon::parse($data['expense_date'])), 'created_by' => auth()->id()]);
            $doc->fill(['expense_date' => $data['expense_date'], 'expense_account_id' => $data['expense_account_id'], 'money_account_id' => $paidFrom,
                'vendor_id' => $vendor, 'amount' => Decimal::round((string) $data['amount']), 'reference' => $data['reference'] ?? null ?: null, 'notes' => $data['notes'] ?? null ?: null])->save();

            return $doc;
        });
    }

    /** Paid now: Dr Expense, Cr cash/bank. Payable: Dr Expense, Cr Accounts Payable (vendor). */
    public function postExpense(Expense $doc, ?string $overrideReason = null): Expense
    {
        return $this->workflow->post($doc, function (Expense $e) {
            $entry = (new JournalEntry($e->expense_date, 'PKR', "{$e->number} {$e->expenseAccount()->firstOrFail()->name}".($e->reference ? " ({$e->reference})" : ''), $e))
                ->debit($e->expenseAccount()->firstOrFail(), $e->amount);
            if ($e->money_account_id) {
                $money = $e->moneyAccount()->firstOrFail();
                $this->accounts->assertCanPay($money, $e->amount);
                $entry->credit($money, $e->amount);
                $e->forceFill(['paid_amount' => $e->amount])->save();
            } else {
                $entry->credit('accounts_payable', $e->amount, Vendor::query()->findOrFail($e->vendor_id));
            }
            $this->journals->post($entry);
        }, $overrideReason);
    }

    // ---- Journal voucher --------------------------------------------------

    /**
     * @param  array<string, mixed>  $header  voucher_date, currency_code, description
     * @param  list<array{account: string, party?: string|null, debit?: string|null, credit?: string|null, memo?: string|null, deduct_from_payroll?: bool}>  $lines
     *                                                                                                                                                               account "gl:ID" or "money:ID"; party "type:ID"
     */
    public function saveVoucher(?JournalVoucher $doc, array $header, array $lines): JournalVoucher
    {
        $this->assertDraft($doc);
        if (trim((string) ($header['description'] ?? '')) === '') {
            throw new DocumentException('A description is required.');
        }
        $rows = [];
        foreach ($lines as $i => $l) {
            $debit = Decimal::round((string) ($l['debit'] ?? '') ?: '0');
            $credit = Decimal::round((string) ($l['credit'] ?? '') ?: '0');
            if (Decimal::isZero($debit) && Decimal::isZero($credit)) {
                continue;
            }
            if (! Decimal::isZero($debit) && ! Decimal::isZero($credit) || Decimal::isNegative($debit) || Decimal::isNegative($credit)) {
                throw new DocumentException('Line '.($i + 1).': enter either a debit or a credit.');
            }
            [$kind, $id] = array_pad(explode(':', (string) $l['account']), 2, null);
            if (! in_array($kind, ['gl', 'money'], true) || ! $id) {
                throw new DocumentException('Line '.($i + 1).': choose an account.');
            }
            $party = null;
            if (! empty($l['party'])) {
                [$ptype, $pid] = explode(':', (string) $l['party']);
                $class = Relation::getMorphedModel($ptype) ?? $ptype;
                $party = class_exists($class) ? $class::query()->find($pid) : null;
                if (! $party instanceof Model) {
                    throw new DocumentException('Line '.($i + 1).': unknown party.');
                }
            }
            $payroll = (bool) ($l['deduct_from_payroll'] ?? false);
            if ($payroll && ! ($party instanceof Employee && Decimal::compare($debit, '0') > 0)) {
                throw new DocumentException('Line '.($i + 1).': only a debit to an employee can be deducted from payroll.');
            }
            $rows[] = ['gl_account_id' => $kind === 'gl' ? (int) $id : null, 'money_account_id' => $kind === 'money' ? (int) $id : null,
                'party_type' => $party?->getMorphClass(), 'party_id' => $party?->getKey(), 'debit' => $debit, 'credit' => $credit,
                'memo' => $l['memo'] ?? null ?: null, 'deduct_from_payroll' => $payroll, 'sort_order' => $i];
        }
        if (count($rows) < 2) {
            throw new DocumentException('A voucher needs at least two lines.');
        }
        $debits = Decimal::sum(array_column($rows, 'debit'));
        $credits = Decimal::sum(array_column($rows, 'credit'));
        if (Decimal::compare($debits, $credits) !== 0) {
            throw new DocumentException("The voucher does not balance: debits {$debits}, credits {$credits}.");
        }
        $currency = $header['currency_code'] ?? 'PKR';
        foreach (array_filter(array_column($rows, 'money_account_id')) as $mid) {
            if (MoneyAccount::query()->whereKey($mid)->value('currency_code') !== $currency) {
                throw new DocumentException("Every cash/bank account on the voucher must be in {$currency}.");
            }
        }

        return DB::transaction(function () use ($doc, $header, $rows, $debits, $currency) {
            $doc ??= new JournalVoucher(['number' => $this->numbers->next('journal_voucher', Carbon::parse($header['voucher_date'])), 'created_by' => auth()->id()]);
            $doc->fill(['voucher_date' => $header['voucher_date'], 'currency_code' => $currency, 'description' => $header['description']])->save();
            $doc->lines()->delete();
            foreach ($rows as $r) {
                $doc->lines()->create($r);
            }
            $doc->forceFill(['total' => $debits])->save();

            return $doc;
        });
    }

    /** Posts the voucher as one balanced journal (JournalPoster refuses an unbalanced one). */
    public function postVoucher(JournalVoucher $doc, ?string $overrideReason = null): JournalVoucher
    {
        return $this->workflow->post($doc, function (JournalVoucher $v) {
            $entry = new JournalEntry($v->voucher_date, $v->currency_code, "{$v->number} {$v->description}", $v);
            foreach ($v->lines()->with(['account', 'moneyAccount', 'party'])->get() as $l) {
                $account = $l->moneyAccount ?? $l->account ?? throw new DocumentException('A voucher line has no account.');
                Decimal::compare($l->debit, '0') > 0
                    ? $entry->debit($account, $l->debit, $l->party, $l->memo)
                    : $entry->credit($account, $l->credit, $l->party, $l->memo);
            }
            $journal = $this->journals->post($entry);
            $v->forceFill(['journal_id' => $journal->id])->save();
        }, $overrideReason);
    }

    /**
     * Posted voucher lines charging an employee that payroll should deduct
     * (not yet deducted), up to a date.
     *
     * @return Collection<int, JournalVoucherLine>
     */
    public function payrollFines(Model $employee, CarbonInterface $until): Collection
    {
        return JournalVoucherLine::query()->with(['account', 'voucher'])
            ->where('party_type', $employee->getMorphClass())->where('party_id', $employee->getKey())
            ->where('deduct_from_payroll', true)->whereNull('payroll_line_id')
            ->whereHas('voucher', fn ($q) => $q->whereNotNull('posted_at')->whereDate('voucher_date', '<=', $until))->get();
    }

    /**
     * Marks fines as deducted by a payroll line. Fines booked to Wages Payable
     * already reduced what the employee is owed; others are moved there now:
     * Dr Wages and Salaries Payable, Cr the account the fine was charged to.
     *
     * @param  list<int>  $ids
     */
    public function deductFines(array $ids, int $payrollLineId, Model $employee, CarbonInterface $date, Model $source): void
    {
        $lines = JournalVoucherLine::query()->with('account')->whereIn('id', $ids)->lockForUpdate()->get();
        foreach ($lines as $l) {
            if ($l->payroll_line_id !== null) {
                throw new DocumentException('A fine on this payroll was already deducted.');
            }
            if ($l->account !== null && $l->account->system_key !== 'wages_payable') {
                $this->journals->post((new JournalEntry($date, 'PKR', 'Fine deducted from payroll', $source))
                    ->debit('wages_payable', $l->debit, $employee)->credit($l->account, $l->debit, $employee));
            }
        }
        JournalVoucherLine::query()->whereIn('id', $ids)->update(['payroll_line_id' => $payrollLineId]);
    }

    private function assertDraft(?Model $doc): void
    {
        if ($doc && $doc->getAttribute('status') !== 'draft') {
            throw new DocumentException("{$doc->getAttribute('number')} is {$doc->getAttribute('status')} and cannot be edited.");
        }
    }

    private function assertPositive(string $amount, string $label): void
    {
        if (! is_numeric($amount) || Decimal::compare($amount, '0') <= 0) {
            throw new DocumentException("{$label} must be above zero.");
        }
    }
}
