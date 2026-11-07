<?php

namespace App\Modules\Reports\Livewire;

use App\Modules\Reports\Services\OrderCosting;
use App\Modules\Sales\Models\SalesOrder;
use App\Support\Decimal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Order cost and estimated profit (specification, Module 12). Revenue stays in
 * the customer's currency; profit uses a PKR reference value typed in here,
 * for reporting only (it posts nothing).
 */
class OrderCost extends Component
{
    #[Locked]
    public SalesOrder $order;

    public string $reference_pkr = '';

    public string $reference_rate = '';

    public function mount(SalesOrder $order): void
    {
        $this->order = $order;
    }

    public function render(OrderCosting $costing): View
    {
        $data = $costing->forOrder($this->order);
        $reference = null;
        if (is_numeric($this->reference_pkr) && (float) $this->reference_pkr > 0) {
            $reference = Decimal::round((string) $this->reference_pkr);
        } elseif (is_numeric($this->reference_rate) && (float) $this->reference_rate > 0) {
            $reference = $data['currency'] === 'PKR' ? $data['revenue'] : Decimal::mul($data['revenue'], (string) $this->reference_rate);
        }
        if ($data['currency'] === 'PKR') {
            $reference = $data['revenue'];
        }

        return view('reports::livewire.order-cost', $data + [
            'reference' => $reference,
            'profit' => $reference !== null ? Decimal::sub($reference, $data['actual']) : null,
            'canProfit' => auth()->user()->can('profit.view'),
        ])->title('Order cost '.$this->order->number);
    }
}
