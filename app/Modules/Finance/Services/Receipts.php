<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Models\ReceiptAllocation;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use App\Support\Finance\Receivable;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Customer receipts (specification, Module 10). Everything stays in the
 * customer's currency; no exchange rate is used.
 *
 *   amount cleared from the customer = deductions + net received
 *   Dr deposit account (net), Dr each deduction head,
 *   Cr Accounts Receivable (allocated to invoices), Cr Customer Advances (rest).
 *
 * The advance can be set against an invoice later: Dr Customer Advances, Cr Accounts Receivable.
 */
class Receipts
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly Receivables $receivables,
    ) {}

    /**
     * @param  array<string, mixed>  $header  receipt_date, customer_id, amount, money_account_id, reference, notes
     * @param  list<array{gl_account_id: int, amount: string, memo?: string|null}>  $deductions
     * @param  list<array{type: string, id: int, amount: string}>  $allocations
     */
    public function save(?Receipt $receipt, array $header, array $deductions, array $allocations): Receipt
    {
        if ($receipt && ! $receipt->isDraft()) {
            throw new DocumentException("{$receipt->number} is {$receipt->status} and cannot be edited.");
        }
        $customer = Customer::query()->findOrFail($header['customer_id']);
        $account = MoneyAccount::query()->findOrFail($header['money_account_id']);
        if ($account->currency_code !== $customer->currency_code) {
            throw new DocumentException("{$customer->name} pays in {$customer->currency_code}; choose a {$customer->currency_code} account. Convert to PKR with a Currency Conversion.");
        }
        if (! $account->is_active) {
            throw new DocumentException("{$account->name} is inactive.");
        }
        $amount = Decimal::round((string) $header['amount']);
        if (Decimal::compare($amount, '0') <= 0) {
            throw new DocumentException('The amount must be above zero.');
        }
        $deductions = array_values(array_filter($deductions, fn ($d) => Decimal::compare((string) ($d['amount'] ?: '0'), '0') > 0));
        $deducted = Decimal::sum(array_map(fn ($d) => (string) $d['amount'], $deductions));
        $net = Decimal::sub($amount, $deducted);
        if (Decimal::isNegative($net)) {
            throw new DocumentException('Deductions are more than the amount cleared.');
        }
        foreach ($deductions as $d) {
            $head = GlAccount::query()->findOrFail($d['gl_account_id']);
            if (! in_array($head->type, ['expense', 'cost', 'asset'], true)) {
                throw new DocumentException("{$head->name} cannot be a deduction head.");
            }
        }
        $allocations = array_values(array_filter($allocations, fn ($a) => Decimal::compare((string) ($a['amount'] ?: '0'), '0') > 0));
        if (Decimal::compare(Decimal::sum(array_map(fn ($a) => (string) $a['amount'], $allocations)), $amount) > 0) {
            throw new DocumentException('Allocations exceed the amount cleared.');
        }

        return DB::transaction(function () use ($receipt, $header, $customer, $amount, $net, $deductions, $allocations) {
            $receipt ??= new Receipt(['number' => $this->numbers->next('receipt', Carbon::parse($header['receipt_date'])), 'created_by' => auth()->id()]);
            $receipt->fill([
                'receipt_date' => $header['receipt_date'], 'customer_id' => $customer->id, 'currency_code' => $customer->currency_code,
                'amount' => $amount, 'money_account_id' => $header['money_account_id'], 'net_received' => $net,
                'reference' => $header['reference'] ?? null ?: null, 'notes' => $header['notes'] ?? null ?: null,
                'planned_allocations' => $allocations ?: null,
            ])->save();
            $receipt->deductions()->delete();
            foreach ($deductions as $d) {
                $receipt->deductions()->create(['gl_account_id' => $d['gl_account_id'], 'amount' => Decimal::round((string) $d['amount']), 'memo' => $d['memo'] ?? null ?: null]);
            }

            return $receipt;
        });
    }

    public function post(Receipt $receipt, ?string $overrideReason = null): Receipt
    {
        return $this->workflow->post($receipt, function (Receipt $r) {
            $customer = $r->customer()->firstOrFail();
            $entry = (new JournalEntry($r->receipt_date, $r->currency_code, "{$r->number} received from {$customer->name}".($r->reference ? " ({$r->reference})" : ''), $r));
            if (Decimal::compare($r->net_received, '0') > 0) {
                $entry->debit($r->moneyAccount()->firstOrFail(), $r->net_received);
            }
            foreach ($r->deductions()->with('account')->get() as $d) {
                $entry->debit($d->account, $d->amount, null, $d->memo ?? 'Deduction');
            }

            $allocated = '0';
            foreach ($r->planned_allocations ?? [] as $row) {
                $target = $this->receivable($row['type'], (int) $row['id']);
                $this->assertAllocatable($target, $customer, $r->currency_code, (string) $row['amount']);
                $amount = Decimal::round((string) $row['amount']);
                $target->applyReceipt($r, $amount, $r->receipt_date);
                ReceiptAllocation::query()->create(['receipt_id' => $r->id, 'receivable_type' => $target->getMorphClass(), 'receivable_id' => $target->getKey(),
                    'allocation_date' => $r->receipt_date, 'amount' => $amount, 'created_by' => auth()->id()]);
                $allocated = Decimal::add($allocated, $amount);
            }
            $advance = Decimal::sub($r->amount, $allocated);
            if (Decimal::compare($allocated, '0') > 0) {
                $entry->credit('accounts_receivable', $allocated, $customer);
            }
            if (Decimal::compare($advance, '0') > 0) {
                $entry->credit('customer_advances', $advance, $customer, 'Advance');
            }
            $this->journals->post($entry);
            $r->forceFill(['allocated_amount' => $allocated])->save();
        }, $overrideReason);
    }

    /** Sets part of a receipt's advance against an invoice: Dr Customer Advances, Cr Accounts Receivable. */
    public function applyAdvance(Receipt $receipt, Model&Receivable $target, string $amount, CarbonInterface $date): ReceiptAllocation
    {
        return DB::transaction(function () use ($receipt, $target, $amount, $date) {
            $r = Receipt::query()->lockForUpdate()->findOrFail($receipt->id);
            $amount = Decimal::round($amount);
            if (! $r->isPosted()) {
                throw new DocumentException('Only posted receipts can be allocated.');
            }
            if (Decimal::compare($amount, '0') <= 0 || Decimal::compare($amount, $r->unallocated()) > 0) {
                throw new DocumentException("Only {$r->unallocated()} of {$r->number} is unallocated.");
            }
            $customer = $r->customer()->firstOrFail();
            $target = $this->receivable($target->getMorphClass(), (int) $target->getKey());
            $this->assertAllocatable($target, $customer, $r->currency_code, $amount);

            $journal = $this->journals->post((new JournalEntry($date, $r->currency_code, "{$r->number} advance applied to {$target->receivableLabel()}", $r))
                ->debit('customer_advances', $amount, $customer)->credit('accounts_receivable', $amount, $customer));
            $target->applyReceipt($r, $amount, $date);
            $r->forceFill(['allocated_amount' => Decimal::add($r->allocated_amount, $amount)])->save();

            return ReceiptAllocation::query()->create(['receipt_id' => $r->id, 'receivable_type' => $target->getMorphClass(), 'receivable_id' => $target->getKey(),
                'allocation_date' => $date->format('Y-m-d'), 'amount' => $amount, 'from_advance' => true, 'journal_id' => $journal->id, 'created_by' => auth()->id()]);
        });
    }

    /** @return Model&Receivable */
    private function receivable(string $type, int $id): Model
    {
        $class = Relation::getMorphedModel($type) ?? $type;
        $model = class_exists($class) ? $class::query()->lockForUpdate()->find($id) : null;
        if (! $model instanceof Receivable || ! $model instanceof Model) {
            throw new DocumentException('An invoice on this receipt no longer exists.');
        }

        return $model;
    }

    private function assertAllocatable(Receivable $target, Customer $customer, string $currency, string $amount): void
    {
        if (! $target->receivableBy($customer) || $target->receivableCurrency() !== $currency) {
            throw new DocumentException("{$target->receivableLabel()} is not this customer's, or is in another currency.");
        }
        if (Decimal::compare($amount, $target->receivableBalance()) > 0) {
            throw new DocumentException("{$target->receivableLabel()} has only {$target->receivableBalance()} left to pay.");
        }
    }

    /**
     * Open invoices of a customer.
     *
     * @return Collection<int, Model&Receivable>
     */
    public function openFor(Customer $customer): Collection
    {
        return $this->receivables->openFor($customer);
    }
}
