<?php

namespace App\Modules\Purchasing\Livewire\Receipts;

use App\Modules\Core\Models\Lookup;
use App\Modules\Core\Models\Stage;
use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Inventory\Models\Location;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\GoodsReceiptLine;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Services\Tolerance;
use App\Support\Decimal;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Draft goods receipt against an open purchase order.
 */
class Form extends Component
{
    #[Locked]
    public ?GoodsReceipt $receipt = null;

    #[Url(as: 'order')]
    #[Locked]
    public ?int $purchase_order_id = null;

    public ?string $receipt_date = null;

    public string $vendor_delivery_note = '';

    public string $notes = '';

    /** @var array<int, array{received_qty: string, accepted_qty: string, rejected_qty: string, rejected_action: string, reject_reason_id: int|string|null, over_receipt_action: string|null, location_id: int|string, stage_id: int|string|null}> */
    public array $lines = [];

    public function mount(?GoodsReceipt $receipt = null): void
    {
        if ($receipt?->exists) {
            abort_unless($receipt->isDraft(), 403, 'Only drafts can be edited.');
            $this->receipt = $receipt;
            $this->purchase_order_id = $receipt->purchase_order_id;
            $this->receipt_date = $receipt->receipt_date->format('Y-m-d');
            $this->vendor_delivery_note = (string) $receipt->vendor_delivery_note;
            $this->notes = (string) $receipt->notes;
            foreach ($receipt->lines as $l) {
                $this->lines[$l->purchase_order_line_id] = [
                    'received_qty' => Decimal::round($l->received_qty, 4), 'accepted_qty' => Decimal::round($l->accepted_qty, 4),
                    'rejected_qty' => Decimal::round($l->rejected_qty, 4), 'rejected_action' => $l->rejected_action,
                    'reject_reason_id' => $l->reject_reason_id, 'over_receipt_action' => $l->over_receipt_action,
                    'location_id' => $l->location_id, 'stage_id' => $l->stage_id,
                ];
            }

            return;
        }

        $order = PurchaseOrder::query()->findOrFail($this->purchase_order_id);
        abort_unless($order->isOpen(), 403, 'This purchase order is not open for receiving.');
        $this->receipt_date = now()->format('Y-m-d');

        foreach ($order->lines as $l) {
            $this->lines[$l->id] = [
                'received_qty' => Decimal::round($l->outstanding(), 4), 'accepted_qty' => Decimal::round($l->outstanding(), 4), 'rejected_qty' => '0',
                'rejected_action' => 'return', 'reject_reason_id' => null, 'over_receipt_action' => null,
                'location_id' => Location::mainStore()->id, 'stage_id' => $l->stage_id,
            ];
        }
    }

    /** Keeps accepted = received − rejected as the user types. */
    public function updatedLines(mixed $value, string $key): void
    {
        [$id, $field] = explode('.', $key) + [1 => ''];
        if (in_array($field, ['received_qty', 'rejected_qty'], true) && isset($this->lines[$id])) {
            $line = &$this->lines[$id];
            if (is_numeric($line['received_qty']) && is_numeric($line['rejected_qty'])) {
                $line['accepted_qty'] = Decimal::sub($line['received_qty'], $line['rejected_qty'], 4);
            }
        }
    }

    public function save(): void
    {
        $this->authorize($this->receipt ? 'goods_receipts.edit' : 'goods_receipts.create');
        $order = PurchaseOrder::query()->with('lines')->findOrFail($this->purchase_order_id);

        $this->validate([
            'receipt_date' => ['required', 'date_format:Y-m-d'],
            'vendor_delivery_note' => ['nullable', 'string', 'max:60'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'lines.*.received_qty' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.accepted_qty' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.rejected_qty' => ['nullable', 'numeric', 'min:0', 'decimal:0,4'],
            'lines.*.rejected_action' => ['required', Rule::in(['return', 'keep'])],
            'lines.*.reject_reason_id' => ['nullable', Rule::exists('lookups', 'id')->where('type', 'rejection_reason')],
            'lines.*.over_receipt_action' => ['nullable', Rule::in(array_keys(GoodsReceiptLine::OVER_RECEIPT_ACTIONS))],
            'lines.*.location_id' => ['required', Rule::exists('locations', 'id')->where('is_active', true)],
            'lines.*.stage_id' => ['nullable', Rule::exists('stages', 'id')],
        ], [], ['lines.*.received_qty' => 'received', 'lines.*.accepted_qty' => 'accepted', 'lines.*.rejected_qty' => 'rejected']);

        $rows = [];
        foreach ($this->lines as $poLineId => $l) {
            if (! is_numeric($l['received_qty']) || Decimal::compare($l['received_qty'], '0') <= 0) {
                continue;
            }
            $poLine = $order->lines->firstWhere('id', (int) $poLineId);
            abort_if($poLine === null, 404);

            if (Decimal::compare($l['received_qty'], Decimal::add($l['accepted_qty'] ?: '0', $l['rejected_qty'] ?: '0', 4)) !== 0) {
                $this->addError("lines.$poLineId.accepted_qty", 'Received must equal accepted + rejected.');

                return;
            }
            if (Decimal::compare($l['rejected_qty'] ?: '0', '0') > 0 && empty($l['reject_reason_id'])) {
                $this->addError("lines.$poLineId.reject_reason_id", 'Give a reason for the rejected quantity.');

                return;
            }

            $rows[] = [
                'purchase_order_line_id' => $poLine->id,
                'item_variant_id' => $poLine->item_variant_id,
                'ordered_qty' => $poLine->quantity,
                'previously_accepted_qty' => $poLine->accepted_qty,
                'received_qty' => $l['received_qty'],
                'accepted_qty' => $l['accepted_qty'] ?: '0',
                'rejected_qty' => $l['rejected_qty'] ?: '0',
                'rejected_action' => $l['rejected_action'],
                'reject_reason_id' => $l['reject_reason_id'] ?: null,
                'over_receipt_action' => $l['over_receipt_action'] ?: null,
                'rate' => $poLine->rate,
                'location_id' => $l['location_id'],
                'stage_id' => $l['stage_id'] ?: null,
            ];
        }

        if ($rows === []) {
            $this->addError('lines', 'Enter a received quantity on at least one line.');

            return;
        }

        $receipt = DB::transaction(function () use ($order, $rows) {
            $receipt = $this->receipt ?? new GoodsReceipt([
                'number' => app(DocumentNumber::class)->next('goods_receipt', Carbon::parse($this->receipt_date)),
                'purchase_order_id' => $order->id,
                'vendor_id' => $order->vendor_id,
                'created_by' => auth()->id(),
            ]);
            $receipt->fill([
                'receipt_date' => $this->receipt_date,
                'vendor_delivery_note' => $this->vendor_delivery_note ?: null,
                'received_by' => auth()->id(),
                'notes' => $this->notes ?: null,
            ])->save();
            $receipt->lines()->delete();
            $receipt->lines()->createMany($rows);

            return $receipt;
        });

        session()->flash('toast', "{$receipt->number} saved as draft.");
        $this->redirectRoute('purchasing.receipts.show', $receipt, navigate: true);
    }

    public function render(Tolerance $tolerance): View
    {
        $order = PurchaseOrder::query()->with(['vendor', 'lines.variant.item', 'lines.unit'])->findOrFail($this->purchase_order_id);
        $limits = $order->lines->mapWithKeys(fn ($l) => [$l->id => [
            'outstanding' => $l->outstanding(),
            'allowed' => $tolerance->allowed($l->outstanding(), $tolerance->percent($l->variant->item, $order->vendor)),
        ]]);

        return view('purchasing::livewire.receipts.form', [
            'order' => $order,
            'limits' => $limits,
            'locations' => Location::query()->active()->where('type', '!=', Location::CONTRACTOR)->orderBy('name')->pluck('name', 'id'),
            'stages' => Stage::query()->ordered()->pluck('name', 'id'),
            'reasons' => Lookup::options('rejection_reason'),
            'overActions' => GoodsReceiptLine::OVER_RECEIPT_ACTIONS,
        ])->title($this->receipt ? 'Edit '.$this->receipt->number : 'Receive goods for '.$order->number);
    }
}
