<div>
    <x-page-header title="Payments" description="Money paid to vendors, contractors, employees and expense heads.">
        @can('payments.create') <x-button :href="route('finance.payments.create')" icon="plus">New payment</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number or reference…" dates :export="auth()->user()->can('payments.export')">
            <select wire:model.live="kind" aria-label="Paid to" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All payees</option>@foreach ($kinds as $k => $v)<option value="{{ $k }}">{{ $v }}</option>@endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No payments.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="payment_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Paid to</x-table.th><x-table.th>From</x-table.th>
                <x-table.th sortable="amount" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Amount</x-table.th>
                <x-table.th align="right">Advance</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($payments as $p)
                <tr wire:key="p-{{ $p->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('finance.payments.show', $p) }}" wire:navigate class="text-brand-600 hover:underline">{{ $p->number }}</a></x-table.td>
                    <x-table.td>@date($p->payment_date)</x-table.td>
                    <x-table.td>{{ $p->payee?->name ?? $p->expenseAccount?->name }} <span class="text-xs text-zinc-500">{{ $kinds[$p->payee_kind] }}</span></x-table.td>
                    <x-table.td>{{ $p->moneyAccount->name }}</x-table.td>
                    <x-table.td align="right">@money($p->amount)</x-table.td>
                    <x-table.td align="right">{{ $p->payee_kind !== 'expense' && $p->isPosted() ? \App\Support\Format::money($p->unallocated()) : '' }}</x-table.td>
                    <x-table.td><x-status :status="$p->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($payments->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $payments->links() }}</div> @endif
    </x-card>
</div>
