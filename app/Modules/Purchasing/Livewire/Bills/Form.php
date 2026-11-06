<?php

namespace App\Modules\Purchasing\Livewire\Bills;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceiptLine;
use App\Modules\Purchasing\Models\VendorBill;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Draft vendor bill for a vendor's posted, not yet billed receipt lines.
 */
class Form extends Component
{
    #[Locked]
    public ?VendorBill $bill = null;

    #[Url(as: 'receipt')]
    public ?int $receiptId = null;

    public ?int $vendor_id = null;

    public string $vendor_invoice_number = '';

    public ?string $bill_date = null;

    public ?string $due_date = null;

    public string $tax_amount = '0';

    public string $charges_amount = '0';

    public string $deductions_amount = '0';

    public string $notes = '';

    /** @var array<int, string> goods receipt line id => quantity to bill */
    public array $quantities = [];

    public function mount(?VendorBill $bill = null): void
    {
        if ($bill?->exists) {
            abort_unless($bill->isDraft(), 403, 'Only drafts can be edited.');
            $this->bill = $bill;
            $this->fill(array_map(fn ($v) => $v ?? '', $bill->only(['vendor_invoice_number', 'tax_amount', 'charges_amount', 'deductions_amount', 'notes'])));
            $this->vendor_id = $bill->vendor_id;
            $this->bill_date = $bill->bill_date->format('Y-m-d');
            $this->due_date = $bill->due_date?->format('Y-m-d');
            $this->quantities = $bill->lines->mapWithKeys(fn ($l) => [$l->goods_receipt_line_id => Decimal::round($l->quantity, 4)])->all();

            return;
        }

        $this->bill_date = now()->format('Y-m-d');

        if ($this->receiptId) {
            $receipt = GoodsReceipt::query()->findOrFail($this->receiptId);
            $this->vendor_id = $receipt->vendor_id;
            foreach ($receipt->lines as $line) {
                $left = Decimal::sub($line->payable_qty, $line->billed_qty, 4);
                if (Decimal::compare($left, '0') > 0) {
                    $this->quantities[$line->id] = $left;
                }
            }
        }
    }

    public function updatedVendorId(): void
    {
        $this->quantities = [];
    }

    public function save(): void
    {
        $this->authorize($this->bill ? 'vendor_bills.edit' : 'vendor_bills.create');

        $data = $this->validate([
            'vendor_id' => ['required', Rule::exists('vendors', 'id')],
            'vendor_invoice_number' => ['required', 'string', 'max:60', Rule::unique('vendor_bills')->where('vendor_id', $this->vendor_id)->ignore($this->bill?->id)],
            'bill_date' => ['required', 'date_format:Y-m-d'],
            'due_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:bill_date'],
            'tax_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'charges_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'deductions_amount' => ['required', 'numeric', 'min:0', 'decimal:0,2'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
        ], [], ['vendor_invoice_number' => 'vendor invoice number']);

        $lines = [];
        $goods = '0';
        foreach ($this->quantities as $lineId => $qty) {
            if (! is_numeric($qty) || Decimal::compare($qty, '0') <= 0) {
                continue;
            }
            $grnLine = GoodsReceiptLine::query()->with('receipt')->findOrFail($lineId);
            abort_unless($grnLine->receipt->vendor_id === (int) $data['vendor_id'] && $grnLine->receipt->posted_at !== null, 403);

            $left = Decimal::sub($grnLine->payable_qty, $grnLine->billed_qty, 4);
            if (Decimal::compare($qty, $left) > 0 && ! auth()->user()?->can('vendor_bills.override')) {
                $this->addError("quantities.$lineId", "Only {$left} left to bill.");

                return;
            }
            $amount = Decimal::mul($qty, $grnLine->rate);
            $goods = Decimal::add($goods, $amount);
            $lines[] = ['goods_receipt_line_id' => $grnLine->id, 'item_variant_id' => $grnLine->item_variant_id, 'quantity' => $qty, 'rate' => $grnLine->rate, 'amount' => $amount];
        }

        if ($lines === []) {
            $this->addError('quantities', 'Bill at least one received line.');

            return;
        }

        $total = Decimal::sub(Decimal::add(Decimal::add($goods, $data['tax_amount']), $data['charges_amount']), $data['deductions_amount']);

        $bill = DB::transaction(function () use ($data, $lines, $goods, $total) {
            $bill = $this->bill ?? new VendorBill(['number' => app(DocumentNumber::class)->next('vendor_bill', Carbon::parse($data['bill_date'])), 'created_by' => auth()->id()]);
            $bill->fill([
                'vendor_id' => $data['vendor_id'], 'vendor_invoice_number' => $data['vendor_invoice_number'],
                'bill_date' => $data['bill_date'], 'due_date' => $data['due_date'] ?: null,
                'goods_amount' => $goods, 'tax_amount' => $data['tax_amount'], 'charges_amount' => $data['charges_amount'],
                'deductions_amount' => $data['deductions_amount'], 'total' => $total, 'notes' => $data['notes'] ?: null,
            ])->save();
            $bill->lines()->delete();
            $bill->lines()->createMany($lines);

            return $bill;
        });

        session()->flash('toast', "{$bill->number} saved as draft.");
        $this->redirectRoute('purchasing.bills.show', $bill, navigate: true);
    }

    public function render(): View
    {
        $open = $this->vendor_id ? GoodsReceiptLine::query()->with(['receipt', 'variant.item', 'orderLine.unit'])
            ->whereHas('receipt', fn ($q) => $q->where('vendor_id', $this->vendor_id)->whereNotNull('posted_at'))
            ->get()->filter(fn ($l) => Decimal::compare($l->payable_qty, $l->billed_qty) > 0 || isset($this->quantities[$l->id])) : collect();

        return view('purchasing::livewire.bills.form', [
            'vendors' => Vendor::query()->orderBy('name')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label()]),
            'openLines' => $open,
        ])->title($this->bill ? 'Edit '.$this->bill->number : 'New vendor bill');
    }
}
