@php($n = $note)
<div>
    <x-page-header :title="'Credit note '.$n->number" :description="$n->customer->name.' · '.\App\Support\Format::date($n->credit_date).' · '.$n->reason" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-document-actions :document="$n" :edit-route="route('delivery.credit-notes.edit', $n)" :print-route="$n->isDraft() ? null : route('delivery.credit-notes.print', $n)" />
        @if ($n->isDraft()) @can('credit_notes.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan @endif
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Lines" :padding="false">
                <x-table>
                    <x-slot:head><x-table.th>Description</x-table.th><x-table.th align="right">Amount</x-table.th><x-table.th align="right">Tax</x-table.th></x-slot:head>
                    @foreach ($n->lines as $l)
                        <tr wire:key="l-{{ $l->id }}"><x-table.td>{{ $l->description }}</x-table.td><x-table.td align="right">@money($l->amount)</x-table.td><x-table.td align="right">@if ($l->tax_rate !== null){{ (float) $l->tax_rate }}% · @money($l->tax_amount)@endif</x-table.td></tr>
                    @endforeach
                    <tr class="font-semibold"><x-table.td>Total</x-table.td><x-table.td colspan="2" align="right">@money($n->total, $n->currency_code)</x-table.td></tr>
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$n" :key="'a-'.$n->id" /></x-card>
        </div>
        <div class="space-y-6">
            @if ($n->isPosted())
                <x-card title="Used against invoices">
                    <ul class="space-y-1 text-sm">
                        @forelse ($allocations as $a)<li><a href="{{ route('delivery.invoices.show', $a->invoice) }}" wire:navigate class="font-mono text-link hover:underline">{{ $a->invoice->number }}</a> · @money($a->amount)</li>@empty<li class="text-subtle">Not used yet.</li>@endforelse
                    </ul>
                    <p class="mt-3 text-sm">Unused credit: <span class="font-semibold">@money($n->unallocated(), $n->currency_code)</span></p>
                </x-card>
            @endif
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Customer"><a href="{{ route('people.customers.show', $n->customer_id) }}" wire:navigate class="text-link hover:underline">{{ $n->customer->label() }}</a></x-people::detail>
                    @if ($n->invoice) <x-people::detail label="Invoice"><a href="{{ route('delivery.invoices.show', $n->invoice) }}" wire:navigate class="font-mono text-link hover:underline">{{ $n->invoice->number }}</a></x-people::detail> @endif
                    @if ($n->deliveryReturn) <x-people::detail label="Return"><a href="{{ route('delivery.returns.show', $n->deliveryReturn) }}" wire:navigate class="font-mono text-link hover:underline">{{ $n->deliveryReturn->number }}</a></x-people::detail> @endif
                    <x-people::detail label="Notes" :value="$n->notes" />
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$n" :key="'n-'.$n->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$n" :key="'h-'.$n->id" /></x-card>
        </div>
    </div>
</div>
