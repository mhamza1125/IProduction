<?php

namespace App\Modules\Finance\Livewire\Vouchers;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Finance\Models\JournalVoucher;
use App\Modules\Finance\Services\MoneyMovements;
use App\Support\Documents\Document;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public JournalVoucher $voucher;

    public function mount(JournalVoucher $voucher): void
    {
        $this->voucher = $voucher;
    }

    protected function model(): Model&Document
    {
        return $this->voucher;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(MoneyMovements::class)->postVoucher($this->voucher, $overrideReason);
    }

    protected function heading(): string
    {
        return 'Journal voucher '.$this->voucher->number;
    }

    protected function details(): array
    {
        return ['Date' => Format::date($this->voucher->voucher_date), 'Currency' => $this->voucher->currency_code, 'Description' => $this->voucher->description,
            'Total' => Format::money($this->voucher->total, $this->voucher->currency_code)];
    }

    protected function editRoute(): string
    {
        return 'finance.vouchers.edit';
    }

    protected function indexRoute(): string
    {
        return 'finance.vouchers.index';
    }

    protected function printRoute(): ?string
    {
        return route('finance.vouchers.print', $this->voucher);
    }

    protected function body(): ?string
    {
        return 'finance::livewire.vouchers.lines';
    }

    protected function bodyData(): array
    {
        return ['lines' => $this->voucher->lines()->with(['account', 'moneyAccount', 'party'])->get()];
    }
}
