<?php

namespace App\Modules\Delivery\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\DeliveryLine;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Models\InvoiceAllocation;
use App\Modules\Delivery\Models\InvoiceLine;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\People\Models\Customer;
use App\Modules\Sales\Services\OrderProgress;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use Brick\Math\RoundingMode;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Invoices (specification, Module 9): created from dispatched delivery lines
 * of one customer and currency; a delivery line is invoiced once; unit price
 * defaults from the order and changing it needs permission; each line may
 * carry an optional tax rate (none by default, e.g. exports).
 *
 * Posting, in the customer's currency: Dr Accounts Receivable (total),
 * Cr Sales (or Sales of Rejected Goods) net of discount, Cr Taxes Payable (tax).
 */
class Invoices
{
    public function __construct(
        private readonly DocumentNumber $numbers,
        private readonly DocumentWorkflow $workflow,
        private readonly JournalPoster $journals,
        private readonly OrderProgress $progress,
    ) {}

    /**
     * Dispatched delivery lines of a customer not yet on a live invoice.
     *
     * @return Collection<int, DeliveryLine>
     */
    public function uninvoiced(Customer $customer, ?int $exceptInvoice = null): Collection
    {
        return DeliveryLine::query()->with(['delivery', 'orderLine.order', 'variant'])
            ->whereHas('delivery', fn ($q) => $q->where('customer_id', $customer->id)->where('status', 'dispatched'))
            ->whereDoesntHave('invoiceLines', fn ($q) => $q->whereHas('invoice', fn ($i) => $i->where('status', '!=', 'cancelled')->when($exceptInvoice, fn ($i) => $i->whereKeyNot($exceptInvoice))))
            ->orderBy('delivery_id')->orderBy('sort_order')->get();
    }

    /**
     * @param  array<string, mixed>  $header  customer_id, invoice_date, due_date, payment_terms, notes
     * @param  list<array{delivery_line_id: int, unit_price?: string|null, discount?: string|null, tax_rate?: string|null, description?: string|null}>  $lines
     */
    public function save(?Invoice $invoice, array $header, array $lines): Invoice
    {
        if ($invoice && ! $invoice->isDraft()) {
            throw new DocumentException("{$invoice->number} is {$invoice->status} and cannot be edited.");
        }
        if ($lines === []) {
            throw new DocumentException('Choose at least one dispatched delivery line.');
        }
        $customer = Customer::query()->findOrFail($header['customer_id']);

        return DB::transaction(function () use ($invoice, $header, $lines, $customer) {
            $available = $this->uninvoiced($customer, $invoice?->id)->keyBy('id');
            $currency = null;
            $rows = [];

            foreach ($lines as $i => $l) {
                $dl = $available[$l['delivery_line_id']] ?? throw new DocumentException('A chosen delivery line is not dispatched for this customer, or is already invoiced.');
                $currency ??= $dl->delivery->currency_code;
                if ($dl->delivery->currency_code !== $currency) {
                    throw new DocumentException('One invoice is for one currency.');
                }
                $orderPrice = $dl->orderLine->unit_price;
                $price = ($l['unit_price'] ?? '') === '' || $l['unit_price'] === null ? $orderPrice : (string) $l['unit_price'];
                if (Decimal::compare($price, $orderPrice) !== 0 && ! auth()->user()?->can('invoices.override')) {
                    throw new DocumentException('Line '.($i + 1).': changing the order price needs permission.');
                }
                if (Decimal::isNegative($price)) {
                    throw new DocumentException('Prices cannot be negative.');
                }
                $qty = Decimal::sub($dl->quantity, $dl->returned_qty, 4);
                $gross = Decimal::mul($qty, $price);
                $discount = ($l['discount'] ?? '') === '' || $l['discount'] === null ? null : Decimal::round((string) $l['discount']);
                if ($discount !== null && (Decimal::isNegative($discount) || Decimal::compare($discount, $gross) > 0)) {
                    throw new DocumentException('Line '.($i + 1).': the discount must be between 0 and the line amount.');
                }
                $rate = ($l['tax_rate'] ?? '') === '' || $l['tax_rate'] === null ? null : Decimal::round((string) $l['tax_rate']);
                if ($rate !== null && (Decimal::isNegative($rate) || Decimal::compare($rate, '100') > 0)) {
                    throw new DocumentException('Tax rates are between 0 and 100%.');
                }
                $amount = Decimal::sub($gross, $discount ?? '0');
                $tax = $rate === null ? '0.00' : (string) Decimal::of($amount)->multipliedBy($rate)->dividedBy('100', 2, RoundingMode::HalfUp);

                $rows[] = [
                    'delivery_line_id' => $dl->id, 'sales_order_line_id' => $dl->sales_order_line_id, 'item_variant_id' => $dl->item_variant_id,
                    'description' => $l['description'] ?? null ?: null, 'quantity' => $qty, 'unit_price' => $price, 'order_price' => $orderPrice,
                    'discount' => $discount, 'tax_rate' => $rate, 'tax_amount' => $tax, 'amount' => $amount, 'sort_order' => $i,
                    'gross' => $gross,
                ];
            }

            $invoice ??= new Invoice(['number' => $this->numbers->next('invoice', Carbon::parse($header['invoice_date'])), 'created_by' => auth()->id()]);
            $invoice->fill([
                'customer_id' => $customer->id, 'currency_code' => $currency, 'invoice_date' => $header['invoice_date'],
                'due_date' => $header['due_date'] ?? null ?: null, 'payment_terms' => $header['payment_terms'] ?? null ?: $customer->payment_terms,
                'notes' => $header['notes'] ?? null ?: null,
            ])->save();
            $invoice->lines()->delete();
            foreach ($rows as $r) {
                unset($r['gross']);
                $invoice->lines()->create($r);
            }
            $subtotal = Decimal::sum(array_column($rows, 'gross'));
            $discounts = Decimal::sum(array_map(fn ($r) => $r['discount'] ?? '0', $rows));
            $tax = Decimal::sum(array_column($rows, 'tax_amount'));
            $invoice->forceFill(['subtotal' => $subtotal, 'discount_total' => $discounts, 'tax_total' => $tax,
                'total' => Decimal::add(Decimal::sub($subtotal, $discounts), $tax)])->save();

            return $invoice;
        });
    }

    public function post(Invoice $invoice, ?string $overrideReason = null): Invoice
    {
        return $this->workflow->post($invoice, function (Invoice $inv) {
            $lines = $inv->lines()->with('orderLine.order')->get();
            // A delivery line is invoiced once, even with two drafts open.
            foreach ($lines as $l) {
                $taken = InvoiceLine::query()->where('delivery_line_id', $l->delivery_line_id)->where('invoice_id', '!=', $inv->id)
                    ->whereHas('invoice', fn ($q) => $q->whereNotNull('posted_at'))->lockForUpdate()->exists();
                if ($taken) {
                    throw new DocumentException('A delivery line on this invoice is already invoiced.');
                }
            }

            $entry = (new JournalEntry($inv->invoice_date, $inv->currency_code, "{$inv->number} {$inv->customer->name}", $inv))
                ->debit('accounts_receivable', $inv->total, $inv->customer);
            foreach ($lines->groupBy(fn ($l) => $l->orderLine->order->sells_rejected_stock ? 'sales_rejected' : 'sales') as $account => $group) {
                $entry->credit($account, Decimal::sum($group->pluck('amount')));
            }
            if (Decimal::compare($inv->tax_total, '0') > 0) {
                $entry->credit('taxes_payable', $inv->tax_total);
            }
            $this->journals->post($entry);

            foreach ($lines as $l) {
                $this->progress->invoiced($l->orderLine, $l->quantity);
            }
        }, $overrideReason);
    }

    /** Sets (part of) a credit note or receipt against a posted invoice. */
    public function allocate(Invoice $invoice, Model $source, string $amount, ?Carbon $date = null): InvoiceAllocation
    {
        return DB::transaction(function () use ($invoice, $source, $amount, $date) {
            $invoice = Invoice::query()->lockForUpdate()->findOrFail($invoice->id);
            if (! $invoice->isPosted()) {
                throw new DocumentException("{$invoice->number} is not posted.");
            }
            if (Decimal::compare($amount, '0') <= 0 || Decimal::compare($amount, $invoice->balance()) > 0) {
                throw new DocumentException("Allocate between 0 and the invoice balance ({$invoice->balance()}).");
            }
            if ($source instanceof CreditNote) {
                if ($source->customer_id !== $invoice->customer_id || $source->currency_code !== $invoice->currency_code) {
                    throw new DocumentException('The credit note is for another customer or currency.');
                }
                if (Decimal::compare($amount, $source->unallocated()) > 0) {
                    throw new DocumentException("Only {$source->unallocated()} of the credit note is unallocated.");
                }
            }

            return InvoiceAllocation::query()->create([
                'invoice_id' => $invoice->id, 'source_type' => $source->getMorphClass(), 'source_id' => $source->getKey(),
                'allocation_date' => ($date ?? now())->format('Y-m-d'), 'amount' => $amount, 'created_by' => auth()->id(),
            ]);
        });
    }
}
