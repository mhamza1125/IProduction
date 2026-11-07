<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Credit notes (specification, Module 9): reduce what the customer owes in
 * the customer's currency, after a return, a price correction or another
 * approved adjustment. Posting: Dr Sales (Dr Taxes Payable for tax),
 * Cr Accounts Receivable. When linked to an invoice the credit is set
 * against it; any rest stays as customer credit (used later, or refunded).
 */
class CreditNotes
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly Invoices $invoices,
    ) {}

    /**
     * @param  array<string, mixed>  $header  customer_id, invoice_id, delivery_return_id, credit_date, reason, notes
     * @param  list<array{description: string, amount: string, tax_rate?: string|null}>  $lines
     */
    public function save(?CreditNote $note, array $header, array $lines): CreditNote
    {
        if ($note && ! $note->isDraft()) {
            throw new DocumentException("{$note->number} is {$note->status} and cannot be edited.");
        }
        if (trim((string) ($header['reason'] ?? '')) === '') {
            throw new DocumentException('A reason is required.');
        }
        $lines = array_values(array_filter($lines, fn ($l) => Decimal::compare((string) ($l['amount'] ?: '0'), '0') > 0));
        if ($lines === []) {
            throw new DocumentException('Add at least one line with an amount.');
        }
        $customer = Customer::query()->findOrFail($header['customer_id']);
        $invoice = empty($header['invoice_id']) ? null : Invoice::query()->findOrFail($header['invoice_id']);
        if ($invoice && ($invoice->customer_id !== $customer->id || ! $invoice->isPosted())) {
            throw new DocumentException('Choose a posted invoice of this customer.');
        }
        $return = empty($header['delivery_return_id']) ? null : DeliveryReturn::query()->findOrFail($header['delivery_return_id']);
        if ($return && $return->customer_id !== $customer->id) {
            throw new DocumentException('That return is from another customer.');
        }

        return DB::transaction(function () use ($note, $header, $lines, $customer, $invoice, $return) {
            $note ??= new CreditNote(['number' => $this->numbers->next('credit_note', Carbon::parse($header['credit_date'])), 'created_by' => auth()->id()]);
            $note->fill([
                'customer_id' => $customer->id, 'currency_code' => $invoice->currency_code ?? $customer->currency_code,
                'invoice_id' => $invoice?->id, 'delivery_return_id' => $return?->id, 'credit_date' => $header['credit_date'],
                'reason' => $header['reason'], 'notes' => $header['notes'] ?? null ?: null,
            ])->save();
            $note->lines()->delete();
            $sub = '0';
            $tax = '0';
            foreach ($lines as $i => $l) {
                $rate = ($l['tax_rate'] ?? '') === '' || $l['tax_rate'] === null ? null : Decimal::round((string) $l['tax_rate']);
                $amount = Decimal::round((string) $l['amount']);
                $t = $rate === null ? '0.00' : (string) Decimal::of($amount)->multipliedBy($rate)->dividedBy('100', 2, RoundingMode::HalfUp);
                $note->lines()->create(['description' => $l['description'], 'amount' => $amount, 'tax_rate' => $rate, 'tax_amount' => $t, 'sort_order' => $i]);
                $sub = Decimal::add($sub, $amount);
                $tax = Decimal::add($tax, $t);
            }
            $note->forceFill(['subtotal' => $sub, 'tax_total' => $tax, 'total' => Decimal::add($sub, $tax)])->save();

            return $note;
        });
    }

    public function post(CreditNote $note, ?string $overrideReason = null): CreditNote
    {
        $note = $this->workflow->post($note, function (CreditNote $n) {
            $entry = (new JournalEntry($n->credit_date, $n->currency_code, "{$n->number} credit to {$n->customer->name}: {$n->reason}", $n))
                ->debit('sales', $n->subtotal);
            if (Decimal::compare($n->tax_total, '0') > 0) {
                $entry->debit('taxes_payable', $n->tax_total);
            }
            $entry->credit('accounts_receivable', $n->total, $n->customer);
            $this->journals->post($entry);
        }, $overrideReason);

        if ($note->invoice_id) {
            $invoice = Invoice::query()->findOrFail($note->invoice_id);
            $amount = Decimal::compare($note->total, $invoice->balance()) < 0 ? $note->total : $invoice->balance();
            if (Decimal::compare($amount, '0') > 0) {
                $this->invoices->allocate($invoice, $note, $amount, $note->credit_date);
            }
        }

        return $note;
    }
}
