<?php

namespace App\Modules\Finance\Livewire\Conversions;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Finance\Models\CurrencyConversion;
use App\Modules\Finance\Services\MoneyMovements;
use App\Support\Documents\Document;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public CurrencyConversion $conversion;

    public function mount(CurrencyConversion $conversion): void
    {
        $this->conversion = $conversion->load(['fromAccount', 'toAccount', 'deductionAccount']);
    }

    protected function model(): Model&Document
    {
        return $this->conversion;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(MoneyMovements::class)->postConversion($this->conversion, $overrideReason);
    }

    protected function heading(): string
    {
        return 'Currency conversion '.$this->conversion->number;
    }

    protected function details(): array
    {
        $c = $this->conversion;

        return [
            'Date' => Format::date($c->conversion_date),
            'From' => $c->fromAccount->name.' · '.Format::money($c->from_amount, $c->fromAccount->currency_code),
            'To' => $c->toAccount->name.' · '.Format::money($c->pkr_received, 'PKR'),
            'Deductions' => $c->deductions ? Format::money($c->deductions, 'PKR').' ('.$c->deductionAccount?->name.')' : null,
            'Effective rate (information)' => $c->effectiveRate(),
            'Reference' => $c->reference,
            'Notes' => $c->notes,
        ];
    }

    protected function editRoute(): string
    {
        return 'finance.conversions.edit';
    }

    protected function indexRoute(): string
    {
        return 'finance.conversions.index';
    }
}
