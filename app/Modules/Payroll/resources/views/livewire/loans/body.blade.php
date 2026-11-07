<x-card title="Installments recovered" :padding="false">
    <x-table empty="Nothing recovered yet.">
        <x-slot:head><x-table.th>Month</x-table.th><x-table.th align="right">Amount</x-table.th></x-slot:head>
        @foreach ($installments as $i)<tr wire:key="li-{{ $i->id }}"><x-table.td>{{ $i->month->format('M Y') }}</x-table.td><x-table.td align="right">@money($i->amount)</x-table.td></tr>@endforeach
    </x-table>
</x-card>
@if ($doc->isPosted())
    <x-card title="Installment changes" description="Reduce, skip (0) or increase one month's installment. The last installment never exceeds the balance.">
        <ul class="mb-4 space-y-1 text-sm">
            @forelse ($changes as $c)<li>{{ $c->month->format('M Y') }}: @money($c->amount) · {{ $c->reason }}</li>@empty<li class="text-zinc-500">None.</li>@endforelse
        </ul>
        @can('loans.override')
            <form wire:submit="changeInstallment" class="flex flex-wrap items-end gap-3">
                <x-input label="Month" type="month" wire:model="change_month" />
                <x-input label="Installment" type="number" step="any" min="0" wire:model="change_amount" />
                <x-input label="Reason" wire:model="change_reason" class="min-w-64 flex-1" />
                <x-button type="submit" size="sm" variant="secondary">Save change</x-button>
            </form>
        @endcan
    </x-card>
@endif
