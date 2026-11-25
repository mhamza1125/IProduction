<?php

namespace App\Modules\Purchasing\Livewire\LandedCosts;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\People\Models\Vendor;
use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\LandedCost;
use App\Modules\Purchasing\Services\LandedCosts;
use App\Support\Documents\DocumentException;
use App\Support\Format;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Form extends Component
{
    #[Locked]
    public ?LandedCost $landedCost = null;

    public string $cost_date = '';

    public string $payment = 'payable';

    public ?int $money_account_id = null;

    public ?int $vendor_id = null;

    public string $method = 'value';

    public string $reference = '';

    public string $notes = '';

    /** @var list<array{charge_type: string, description: string, amount: string}> */
    public array $charges = [];

    /** @var list<int|string> */
    public array $receipt_ids = [];

    public string $receiptSearch = '';

    public function mount(?LandedCost $landedCost = null): void
    {
        if ($landedCost?->exists) {
            abort_unless($landedCost->isDraft(), 403, 'Only drafts can be edited.');
            $this->landedCost = $landedCost;
            $this->cost_date = $landedCost->cost_date->format('Y-m-d');
            $this->payment = $landedCost->money_account_id ? 'now' : 'payable';
            $this->money_account_id = $landedCost->money_account_id;
            $this->vendor_id = $landedCost->vendor_id;
            $this->method = $landedCost->method;
            $this->reference = (string) $landedCost->reference;
            $this->notes = (string) $landedCost->notes;
            $this->charges = $landedCost->charges()->get()->map(fn ($c) => ['charge_type' => $c->charge_type, 'description' => (string) $c->description, 'amount' => (string) $c->amount])->all();
            $this->receipt_ids = $landedCost->receipts()->pluck('goods_receipts.id')->all();

            return;
        }
        $this->cost_date = now()->format('Y-m-d');
        $this->charges = [['charge_type' => 'freight', 'description' => '', 'amount' => '']];
        if ($grn = request()->integer('receipt')) {
            $this->receipt_ids = [$grn];
        }
    }

    public function addCharge(): void
    {
        $this->charges[] = ['charge_type' => 'other', 'description' => '', 'amount' => ''];
    }

    public function removeCharge(int $i): void
    {
        unset($this->charges[$i]);
        $this->charges = array_values($this->charges);
    }

    public function save(LandedCosts $service): void
    {
        $this->authorize($this->landedCost ? 'landed_costs.edit' : 'landed_costs.create');
        $this->validate([
            'cost_date' => ['required', 'date'], 'payment' => ['required', 'in:now,payable'], 'method' => ['required', 'in:value,quantity'],
            'money_account_id' => [$this->payment === 'now' ? 'required' : 'nullable', 'exists:money_accounts,id'],
            'vendor_id' => [$this->payment === 'payable' ? 'required' : 'nullable', 'exists:vendors,id'],
            'reference' => ['nullable', 'string', 'max:100'], 'notes' => ['nullable', 'string', 'max:1000'],
            'charges' => ['required', 'array', 'min:1'], 'charges.*.charge_type' => ['required', 'in:'.implode(',', array_keys(LandedCost::CHARGE_TYPES))],
            'charges.*.description' => ['nullable', 'string', 'max:255'], 'charges.*.amount' => ['required', 'numeric', 'gt:0', 'decimal:0,2'],
            'receipt_ids' => ['required', 'array', 'min:1'], 'receipt_ids.*' => ['integer', 'exists:goods_receipts,id'],
        ], ['receipt_ids.required' => 'Choose the goods receipts these charges are for.']);

        try {
            $doc = $service->save($this->landedCost, [
                'cost_date' => $this->cost_date, 'method' => $this->method, 'reference' => $this->reference ?: null, 'notes' => $this->notes ?: null,
                'money_account_id' => $this->payment === 'now' ? $this->money_account_id : null,
                'vendor_id' => $this->payment === 'payable' ? $this->vendor_id : null,
            ], $this->charges, array_map('intval', $this->receipt_ids));
        } catch (DocumentException $e) {
            $this->addError('receipt_ids', $e->getMessage());

            return;
        }
        session()->flash('toast', "{$doc->number} saved.");
        $this->redirectRoute('purchasing.landed-costs.show', $doc, navigate: true);
    }

    public function render(): View
    {
        $term = trim($this->receiptSearch);
        $receipts = GoodsReceipt::query()->with('order.vendor')->whereNotNull('posted_at')
            ->where(fn ($q) => $q->whereIn('id', array_map('intval', $this->receipt_ids))
                ->orWhere(fn ($q) => $q->where('receipt_date', '>=', now()->subMonths(6)->toDateString())
                    ->when($term !== '', fn ($q) => $q->where('number', 'like', '%'.addcslashes($term, '%_\\').'%'))))
            ->latest('receipt_date')->limit(100)->get();

        return view('purchasing::livewire.landed-costs.form', [
            'accounts' => MoneyAccount::query()->active()->where('currency_code', 'PKR')->pluck('name', 'id'),
            'vendors' => Vendor::query()->active()->orderBy('name')->get()->mapWithKeys(fn ($v) => [$v->id => $v->label()]),
            'receipts' => $receipts->map(fn (GoodsReceipt $r) => ['id' => $r->id, 'label' => $r->number.' · '.Format::date($r->receipt_date).' · '.$r->order->vendor->name.' · '.Format::money($r->total_value, 'PKR')]),
            'types' => LandedCost::CHARGE_TYPES,
        ])->title($this->landedCost ? 'Edit '.$this->landedCost->number : 'New landed cost');
    }
}
