<?php

namespace App\Modules\Finance\Livewire\Receipts;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Finance\Models\Receipt;
use App\Modules\Finance\Services\Receipts;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public Receipt $receipt;

    public ?string $target = null;

    public string $apply_amount = '';

    public function mount(Receipt $receipt): void
    {
        $this->receipt = $receipt;
    }

    protected function model(): Model&Document
    {
        return $this->receipt;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(Receipts::class)->post($this->receipt, $overrideReason);
    }

    public function applyAdvance(Receipts $receipts): void
    {
        $this->authorize('receipts.post');
        $this->validate(['target' => ['required', 'string'], 'apply_amount' => ['required', 'numeric', 'gt:0']]);
        $invoice = $receipts->openFor($this->receipt->customer()->firstOrFail())->first(fn ($r) => $r->getMorphClass().':'.$r->getKey() === $this->target) ?? abort(404);
        try {
            $receipts->applyAdvance($this->receipt, $invoice, (string) $this->apply_amount, Carbon::now());
        } catch (DocumentException $e) {
            $this->addError('apply_amount', $e->getMessage());

            return;
        }
        $this->reset('target', 'apply_amount');
        $this->receipt->refresh();
        $this->dispatch('toast', message: 'Advance applied to the invoice.');
    }

    protected function heading(): string
    {
        return 'Customer receipt '.$this->receipt->number;
    }

    protected function details(): array
    {
        $r = $this->receipt->load(['customer', 'moneyAccount']);

        return [
            'Date' => Format::date($r->receipt_date), 'Customer' => $r->customer->label(), 'Deposited to' => $r->moneyAccount->name,
            'Amount cleared' => Format::money($r->amount, $r->currency_code), 'Net received' => Format::money($r->net_received, $r->currency_code),
            'Reference' => $r->reference, 'Notes' => $r->notes,
        ];
    }

    protected function editRoute(): string
    {
        return 'finance.receipts.edit';
    }

    protected function indexRoute(): string
    {
        return 'finance.receipts.index';
    }

    protected function printRoute(): ?string
    {
        return route('finance.receipts.print', $this->receipt);
    }

    protected function body(): ?string
    {
        return 'finance::livewire.receipts.body';
    }

    protected function bodyData(): array
    {
        $r = $this->receipt;
        $open = $r->isPosted() && (float) $r->unallocated() > 0 ? app(Receipts::class)->openFor($r->customer()->firstOrFail())->filter(fn ($i) => $i->receivableCurrency() === $r->currency_code) : collect();

        return [
            'deductions' => $r->deductions()->with('account')->get(),
            'allocations' => $r->allocations()->with('receivable')->get(),
            'planned' => $r->planned_allocations ?? [],
            'openInvoices' => $open->mapWithKeys(fn ($i) => [$i->getMorphClass().':'.$i->getKey() => $i->receivableLabel().' · '.$i->receivableBalance()]),
        ];
    }
}
