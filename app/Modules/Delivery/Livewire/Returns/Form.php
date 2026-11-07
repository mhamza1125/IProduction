<?php

namespace App\Modules\Delivery\Livewire\Returns;

use App\Modules\Delivery\Models\DeliveryLine;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Services\DeliveryReturns;
use App\Modules\People\Models\Customer;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?DeliveryReturn $return = null;

    public ?int $customer_id = null;

    public ?int $delivery_line_id = null;

    public ?int $lot_id = null;

    public string $quantity = '';

    public string $reason = '';

    public string $return_date = '';

    public bool $replacement_required = false;

    public string $notes = '';

    public function mount(?DeliveryReturn $return = null): void
    {
        if ($return?->exists) {
            abort_unless($return->isDraft(), 403, 'Only drafts can be edited.');
            $this->return = $return;
            $this->fill($return->only(['customer_id', 'delivery_line_id', 'lot_id', 'reason', 'replacement_required']));
            $this->quantity = Decimal::round($return->quantity, 4);
            $this->return_date = $return->return_date->format('Y-m-d');
            $this->notes = (string) $return->notes;

            return;
        }
        $this->return_date = now()->format('Y-m-d');
    }

    public function save(DeliveryReturns $returns): void
    {
        $this->authorize($this->return ? 'delivery_returns.edit' : 'delivery_returns.create');
        $this->validate([
            'customer_id' => ['required', 'exists:customers,id'],
            'delivery_line_id' => ['required', 'exists:delivery_lines,id'],
            'lot_id' => ['nullable', 'exists:lots,id'],
            'quantity' => ['required', 'numeric', 'gt:0'],
            'reason' => ['required', 'string', 'max:500'],
            'return_date' => ['required', 'date'],
        ]);
        try {
            $r = $returns->save($this->return, ['delivery_line_id' => $this->delivery_line_id, 'lot_id' => $this->lot_id, 'quantity' => $this->quantity, 'reason' => $this->reason,
                'return_date' => $this->return_date, 'replacement_required' => $this->replacement_required, 'notes' => $this->notes]);
        } catch (DocumentException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$r->number} saved.");
        $this->redirectRoute('delivery.returns.show', $r, navigate: true);
    }

    public function render(): View
    {
        $lines = $this->customer_id ? DeliveryLine::query()->with(['delivery', 'variant', 'orderLine.order'])
            ->whereHas('delivery', fn ($q) => $q->where('customer_id', $this->customer_id)->where('status', 'dispatched'))->latest('id')->limit(200)->get() : collect();
        $line = $lines->firstWhere('id', $this->delivery_line_id);

        return view('delivery::livewire.returns.form', [
            'customers' => Customer::query()->orderBy('name')->pluck('name', 'id'),
            'lines' => $lines->mapWithKeys(fn ($l) => [$l->id => $l->delivery->number.' · '.$l->orderLine->order->number.' · '.$l->variant->sku.' · '.Decimal::round($l->quantity, 4)]),
            'lots' => $line ? $line->allocations()->with('lot')->get()->mapWithKeys(fn ($a) => [$a->lot_id => $a->lot->lot_number.' · '.Decimal::round($a->quantity, 4)]) : collect(),
        ])->title($this->return ? 'Edit '.$this->return->number : 'New delivery return');
    }
}
