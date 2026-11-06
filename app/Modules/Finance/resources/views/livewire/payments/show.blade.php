<div>
    <x-page-header :title="'Payment '.$payment->number" :description="($payment->payee?->name ?? $payment->expenseAccount?->name).' · '.\App\Support\Format::date($payment->payment_date).' · PKR '.\App\Support\Format::money($payment->amount)">
        @if ($payment->isDraft())
            @can('payments.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
    </x-page-header>
    <div class="mb-6"><x-document-actions :document="$payment" :edit-route="route('finance.payments.edit', $payment)" :print-route="route('finance.payments.print', $payment)" /></div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Payment">
                <dl class="grid gap-4 sm:grid-cols-3">
                    <x-people::detail label="Paid to" :value="($payment->payee?->name ?? $payment->expenseAccount?->name).' ('.\App\Modules\Finance\Models\Payment::KINDS[$payment->payee_kind].')'" />
                    <x-people::detail label="From account" :value="$payment->moneyAccount->name" />
                    <x-people::detail label="Method" :value="$payment->method?->name" />
                    <x-people::detail label="Reference" :value="$payment->reference" />
                    <x-people::detail label="Amount" :value="'PKR '.\App\Support\Format::money($payment->amount)" />
                    @if ($payment->payee_kind !== 'expense')
                        <x-people::detail label="Allocated / advance" :value="\App\Support\Format::money($payment->isPosted() ? $payment->allocated_amount : collect($payment->planned_allocations ?? [])->sum('amount')).' / '.\App\Support\Format::money($payment->isPosted() ? $payment->unallocated() : '')" />
                    @endif
                    <x-people::detail label="Notes" :value="$payment->notes" class="sm:col-span-3" />
                </dl>
            </x-card>
            @if ($payment->payee_kind !== 'expense')
                <x-card title="Allocations" :padding="false">
                    <x-table empty="Not allocated.">
                        <x-slot:head><x-table.th>Document</x-table.th><x-table.th align="right">Amount</x-table.th><x-table.th>When</x-table.th></x-slot:head>
                        @if ($payment->isPosted())
                            @foreach ($allocations as $a)
                                <tr><x-table.td>{{ $a->allocatable?->payableLabel() }}</x-table.td><x-table.td align="right">@money($a->amount)</x-table.td><x-table.td>{{ $a->journal_id ? 'Advance applied '.\App\Support\Format::date($a->created_at) : 'With payment' }}</x-table.td></tr>
                            @endforeach
                        @else
                            @foreach ($payment->planned_allocations ?? [] as $a)
                                <tr><x-table.td>{{ class_basename(\Illuminate\Database\Eloquent\Relations\Relation::getMorphedModel($a['type']) ?? $a['type']) }} #{{ $a['id'] }}</x-table.td><x-table.td align="right">@money($a['amount'])</x-table.td><x-table.td>Planned</x-table.td></tr>
                            @endforeach
                        @endif
                    </x-table>
                    @if ($open->isNotEmpty())
                        @can('payments.post')
                            <form wire:submit="allocate" class="flex flex-wrap items-end gap-2 border-t border-zinc-200 p-4 dark:border-zinc-800">
                                <x-select label="Apply advance to" wire:model="allocateTo" :options="$open->mapWithKeys(fn ($o) => [$o->getMorphClass().':'.$o->getKey() => $o->payableLabel().' — open '.\App\Support\Format::money($o->payableBalance())])->all()" placeholder="Choose…" class="min-w-72" />
                                <x-input label="Amount" type="number" step="0.01" min="0" wire:model="allocateAmount" class="w-36" />
                                <x-button type="submit" variant="secondary">Apply</x-button>
                            </form>
                        @endcan
                    @endif
                </x-card>
            @endif
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$payment" :key="'a-'.$payment->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Notes"><livewire:core::shared.notes :model="$payment" :key="'n-'.$payment->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$payment" :key="'h-'.$payment->id" /></x-card>
        </div>
    </div>
</div>
