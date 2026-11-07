<?php

namespace App\Modules\Delivery\Livewire\Invoices;

use App\Modules\Delivery\Livewire\DocumentList;
use App\Modules\Delivery\Models\Invoice;
use App\Support\Format;
use Illuminate\Database\Eloquent\Builder;

class Index extends DocumentList
{
    protected function baseQuery(): Builder
    {
        return Invoice::query()->with(['customer']);
    }

    protected function dateColumn(): string
    {
        return 'invoice_date';
    }

    protected function permission(): string
    {
        return 'invoices';
    }

    protected function title(): string
    {
        return 'Invoices';
    }

    protected function description(): string
    {
        return "The customer owes money when an invoice is posted, in the customer's currency.";
    }

    protected function showRoute(): string
    {
        return 'delivery.invoices.show';
    }

    protected function createRoute(): ?string
    {
        return 'delivery.invoices.create';
    }

    protected function statuses(): array
    {
        return ['draft', 'approved', 'posted', 'cancelled'];
    }

    /** @return array<string, callable(Invoice): string> */
    protected function columns(): array
    {
        return [
            'Customer' => fn (Invoice $i) => $i->customer->name,
            'Due' => fn (Invoice $i) => Format::date($i->due_date),
            'Total' => fn (Invoice $i) => Format::money($i->total, $i->currency_code),
            'Balance' => fn (Invoice $i) => $i->isPosted() ? Format::money($i->balance(), $i->currency_code) : '',
        ];
    }

    protected function rightAligned(): array
    {
        return ['Total', 'Balance'];
    }
}
