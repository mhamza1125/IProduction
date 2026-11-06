<?php

namespace App\Modules\Purchasing\Livewire\Returns;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\StockBalance;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Draft purchase return from the lots of one posted goods receipt.
 */
class Form extends Component
{
    #[Url(as: 'receipt')]
    #[Locked]
    public ?int $receiptId = null;

    public ?string $return_date = null;

    public string $reason = '';

    public string $notes = '';

    public bool $replacement_required = false;

    /** @var array<int, string> stock balance id => quantity to return */
    public array $quantities = [];

    public function mount(): void
    {
        $receipt = GoodsReceipt::query()->findOrFail($this->receiptId);
        abort_unless($receipt->isPosted(), 403, 'Only posted receipts can be returned.');
        $this->return_date = now()->format('Y-m-d');
    }

    /**
     * Stock still held from this receipt's lots (Available or Rejected), per bucket.
     *
     * @return Collection<int, StockBalance>
     */
    private function buckets(GoodsReceipt $receipt): Collection
    {
        return StockBalance::query()->with(['variant.item.stockUnit', 'location', 'lot'])
            ->whereIn('lot_id', $receipt->lines()->whereNotNull('lot_id')->pluck('lot_id'))
            ->whereIn('status', ['available', 'rejected'])
            ->where('quantity', '>', 0)->get();
    }

    public function save(): void
    {
        $this->authorize('purchase_returns.create');
        $receipt = GoodsReceipt::query()->with('lines')->findOrFail($this->receiptId);

        $data = $this->validate([
            'return_date' => ['required', 'date_format:Y-m-d'],
            'reason' => ['required', 'string', 'max:500'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'replacement_required' => ['boolean'],
            'quantities.*' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
        ]);

        $buckets = $this->buckets($receipt)->keyBy('id');
        $lines = [];
        foreach ($this->quantities as $balanceId => $qty) {
            if (! is_numeric($qty) || Decimal::compare($qty, '0') <= 0) {
                continue;
            }
            $bucket = $buckets[$balanceId] ?? abort(404);
            if (Decimal::compare($qty, $bucket->quantity) > 0) {
                $this->addError("quantities.$balanceId", 'More than is in stock from this lot.');

                return;
            }
            $grnLine = $receipt->lines->firstWhere('lot_id', $bucket->lot_id);
            $lines[] = [
                'goods_receipt_line_id' => $grnLine->id, 'item_variant_id' => $bucket->item_variant_id, 'lot_id' => $bucket->lot_id,
                'status' => $bucket->status, 'location_id' => $bucket->location_id, 'quantity' => $qty,
                'rate' => $bucket->lot->unit_cost ?? '0', 'amount' => Decimal::mul($qty, $bucket->lot->unit_cost ?? '0'),
            ];
        }

        if ($lines === []) {
            $this->addError('quantities', 'Enter a quantity to return.');

            return;
        }

        $return = DB::transaction(function () use ($receipt, $data, $lines) {
            $return = PurchaseReturn::query()->create([
                'number' => app(DocumentNumber::class)->next('purchase_return', Carbon::parse($data['return_date'])),
                'vendor_id' => $receipt->vendor_id, 'goods_receipt_id' => $receipt->id, 'return_date' => $data['return_date'],
                'reason' => $data['reason'], 'notes' => $data['notes'] ?: null, 'replacement_required' => $data['replacement_required'],
                'total' => Decimal::sum(array_column($lines, 'amount')), 'created_by' => auth()->id(),
            ]);
            $return->lines()->createMany($lines);

            return $return;
        });

        session()->flash('toast', "{$return->number} saved as draft.");
        $this->redirectRoute('purchasing.returns.show', $return, navigate: true);
    }

    public function render(): View
    {
        $receipt = GoodsReceipt::query()->with('vendor')->findOrFail($this->receiptId);

        return view('purchasing::livewire.returns.form', ['receipt' => $receipt, 'buckets' => $this->buckets($receipt)])
            ->title('Return goods from '.$receipt->number);
    }
}
