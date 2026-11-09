<div x-data="{ ask: null }">
    <x-page-header :title="'Contractor job '.$job->number" :description="$job->contractor->name.' · '.\App\Support\Format::date($job->job_date).($job->due_date ? ' · due '.\App\Support\Format::date($job->due_date) : '')">
        <x-status :status="$job->status" />
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($job->isDraft())
            @can('contractor_jobs.edit') <x-button variant="secondary" :href="route('production.jobs.edit', $job)" icon="pencil">Edit</x-button> @endcan
            @can('contractor_jobs.approve') <x-button wire:click="approve" wire:confirm="Approve {{ $job->number }}?">Approve</x-button> @endcan
            @can('contractor_jobs.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
        @if ($job->status === 'approved')
            @can('contractor_jobs.approve') <x-button variant="ghost" x-on:click="ask = 'draft'">Return to draft</x-button> @endcan
            @can('contractor_jobs.cancel') <x-button variant="ghost" x-on:click="ask = 'cancel'">Cancel</x-button> @endcan
        @endif
        @if ($job->isOpen())
            @can('contractor_jobs.cancel') <x-button variant="ghost" x-on:click="ask = 'close'">Close job</x-button> @endcan
        @endif
        @if (! $job->isDraft())
            @can('contractor_jobs.print') <a href="{{ route('production.jobs.print', $job) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-muted hover:bg-hover"><x-icon name="printer" class="size-4" /> Print</a> @endcan
        @endif
        <div x-show="ask" x-cloak class="mt-2 flex w-full flex-wrap items-end gap-2 rounded-md bg-sunken p-3">
            <x-input label="Reason" wire:model="reason" class="min-w-64 flex-1" />
            <x-button x-show="ask === 'draft'" wire:click="returnToDraft" x-on:click="ask = null">Return to draft</x-button>
            <x-button x-show="ask === 'cancel'" variant="danger" wire:click="cancel" x-on:click="ask = null">Cancel job</x-button>
            <x-button x-show="ask === 'close'" variant="danger" wire:click="close" x-on:click="ask = null">Close job</x-button>
            <x-button variant="ghost" x-on:click="ask = null">Close</x-button>
        </div>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Output" :padding="false">
                <x-table>
                    <x-slot:head><x-table.th>Item</x-table.th><x-table.th>Stage</x-table.th><x-table.th align="right">Agreed</x-table.th>@can('cost.view')<x-table.th align="right">Rate</x-table.th>@endcan<x-table.th align="right">Received</x-table.th><x-table.th align="right">Accepted</x-table.th><x-table.th align="right">Rejected</x-table.th><x-table.th align="right">Paid</x-table.th><x-table.th align="right">Short</x-table.th></x-slot:head>
                    @foreach ($job->lines as $l)
                        <tr wire:key="l-{{ $l->id }}">
                            <x-table.td>{{ $l->variant->fullLabel() }}</x-table.td><x-table.td>{{ $l->stage?->name ?? 'Finished' }}</x-table.td>
                            <x-table.td align="right">@qty($l->quantity)</x-table.td>@can('cost.view')<x-table.td align="right">@money($l->rate, null, 4)</x-table.td>@endcan
                            <x-table.td align="right">@qty($l->received_qty)</x-table.td><x-table.td align="right">@qty($l->accepted_qty)</x-table.td>
                            <x-table.td align="right">@qty($l->rejected_qty)</x-table.td><x-table.td align="right">@qty($l->paid_qty)</x-table.td><x-table.td align="right">@qty($l->short())</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>

            @if ($job->isOpen())
                @can('contractor_jobs.post')
                    <div class="grid gap-6 lg:grid-cols-2">
                        <x-card title="Issue material">
                            <form wire:submit="issue" class="grid gap-3">
                                <x-select label="Material" wire:model="issue_variant_id" :options="$materialOptions" placeholder="Choose…" />
                                <x-input label="Quantity" type="number" step="any" min="0" wire:model="issue_qty" />
                                <x-date-input label="Date" wire:model="date" />
                                <x-button type="submit">Issue to contractor</x-button>
                            </form>
                        </x-card>
                        <x-card title="Receive output">
                            <form wire:submit="receive" class="grid gap-3 sm:grid-cols-2">
                                <x-select label="Line" wire:model="line_id" :options="$job->lines->mapWithKeys(fn ($l) => [$l->id => $l->variant->sku])" placeholder="Choose…" class="sm:col-span-2" />
                                <x-input label="Received" type="number" step="any" min="0" wire:model="received" />
                                <x-input label="Accepted" type="number" step="any" min="0" wire:model="accepted" />
                                <x-input label="Rejected" type="number" step="any" min="0" wire:model="rejected" />
                                <x-input label="Rejection reason" wire:model="reject_reason" />
                                <x-select label="If more than agreed" wire:model="over_action" :options="$overActions" placeholder="—" class="sm:col-span-2" />
                                <x-button type="submit" class="sm:col-span-2">Receive</x-button>
                            </form>
                        </x-card>
                    </div>
                @endcan
            @endif

            @if (! $job->isDraft())
                <x-card title="Material reconciliation" description="Issued = used + returned + wastage + still held by the contractor." :padding="false">
                    <x-table empty="No material issued.">
                        <x-slot:head><x-table.th>Material</x-table.th><x-table.th align="right">Expected</x-table.th><x-table.th align="right">Issued</x-table.th><x-table.th align="right">Used</x-table.th><x-table.th align="right">Returned</x-table.th><x-table.th align="right">Wastage</x-table.th><x-table.th align="right">Held</x-table.th></x-slot:head>
                        @foreach ($reconciliation as $r)
                            <tr wire:key="r-{{ $r['variant']->id }}">
                                <x-table.td>{{ $r['variant']->fullLabel() }}</x-table.td><x-table.td align="right">@qty($r['expected'])</x-table.td><x-table.td align="right">@qty($r['issued'])</x-table.td>
                                <x-table.td align="right">@qty($r['consumed'])</x-table.td><x-table.td align="right">@qty($r['returned'])</x-table.td><x-table.td align="right">@qty($r['wastage'])</x-table.td>
                                <x-table.td align="right" class="font-semibold">@qty($r['held'])</x-table.td>
                            </tr>
                        @endforeach
                    </x-table>
                    @if ($heldRows->isNotEmpty() && $job->isOpen())
                        @can('contractor_jobs.post')
                            <div class="space-y-2 border-t border-line p-4">
                                @foreach ($heldRows as $h)
                                    @php($key = $h['item_variant_id'].'-'.$h['lot_id'])
                                    <div wire:key="hr-{{ $key }}" class="flex flex-wrap items-end gap-2 text-sm">
                                        <span class="min-w-48">{{ $heldNames[$h['item_variant_id']]?->fullLabel() }} · lot {{ $h['lot_id'] }} · held @qty($h['quantity'])</span>
                                        <input type="number" step="any" min="0" wire:model="held.{{ $key }}" aria-label="Quantity" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface">
                                        <x-button size="sm" variant="secondary" wire:click="settleHeld('{{ $key }}', 'return')">Return to store</x-button>
                                        <x-button size="sm" variant="ghost" wire:click="settleHeld('{{ $key }}', 'waste')">Wastage</x-button>
                                        @error("held.$key") <span class="text-xs text-danger">{{ $message }}</span> @enderror
                                    </div>
                                @endforeach
                                <x-input label="Reason (required for wastage)" wire:model="reason" class="max-w-md" />
                            </div>
                        @endcan
                    @endif
                </x-card>

                <x-card title="Transactions" :padding="false">
                    <x-table empty="Nothing recorded yet.">
                        <x-slot:head><x-table.th>Date</x-table.th><x-table.th>Type</x-table.th><x-table.th>Item / lot</x-table.th><x-table.th align="right">Qty</x-table.th><x-table.th align="right">Accepted · rejected · paid</x-table.th>@can('cost.view')<x-table.th align="right">Amount</x-table.th>@endcan<x-table.th>Note</x-table.th></x-slot:head>
                        @foreach ($transactions as $t)
                            <tr wire:key="t-{{ $t->id }}">
                                <x-table.td>@date($t->transaction_date)</x-table.td><x-table.td>{{ \App\Modules\Production\Models\ContractorJobTransaction::TYPES[$t->type] }}</x-table.td>
                                <x-table.td class="text-xs">{{ $t->variant->sku }}@if ($t->lot) · {{ $t->lot->lot_number }}@endif</x-table.td>
                                <x-table.td align="right">@qty($t->quantity)</x-table.td>
                                <x-table.td align="right" class="text-xs">@if ($t->type === 'receipt')@qty($t->accepted_qty) · @qty($t->rejected_qty) · @qty($t->paid_qty)@endif</x-table.td>
                                @can('cost.view')<x-table.td align="right">@if ($t->amount !== null)@money($t->amount)@endif</x-table.td>@endcan
                                <x-table.td class="text-xs">{{ $t->over_receipt_action ? \App\Modules\Production\Services\ContractorJobs::OVER_RECEIPT_ACTIONS[$t->over_receipt_action] : '' }} {{ $t->reason }}</x-table.td>
                            </tr>
                        @endforeach
                    </x-table>
                </x-card>
            @endif
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$job" :key="'a-'.$job->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Contractor"><a href="{{ route('people.contractors.show', $job->contractor_id) }}" wire:navigate class="text-link hover:underline">{{ $job->contractor->name }}</a></x-people::detail>
                    <x-people::detail label="Order line">@if ($job->orderLine)<a href="{{ route('sales.orders.show', $job->orderLine->sales_order_id) }}" wire:navigate class="text-link hover:underline">{{ $job->orderLine->order->number }}</a>@else — @endif</x-people::detail>
                    <x-people::detail label="Payment basis" :value="\Illuminate\Support\Str::headline($job->payment_basis)" />
                    <x-people::detail label="Notes" :value="$job->notes" />
                    @if ($job->close_reason) <x-people::detail label="Close reason" :value="$job->close_reason" /> @endif
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$job" :key="'n-'.$job->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$job" :key="'h-'.$job->id" /></x-card>
        </div>
    </div>
</div>
