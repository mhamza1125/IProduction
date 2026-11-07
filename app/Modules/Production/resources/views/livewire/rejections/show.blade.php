<div>
    <x-page-header :title="'Rejection '.$rejection->number" :description="$rejection->variant->fullLabel().($rejection->stage ? ' · '.$rejection->stage->name : '').' · '.\App\Support\Format::qty($rejection->quantity).' pieces · '.$rejection->reason">
        <x-status :status="$rejection->status" />
    </x-page-header>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Decisions" :padding="false">
                <x-table empty="No decisions yet.">
                    <x-slot:head><x-table.th>Number</x-table.th><x-table.th>Decision</x-table.th><x-table.th align="right">Qty</x-table.th><x-table.th align="right">Amount</x-table.th><x-table.th>Details</x-table.th><x-table.th>Status</x-table.th><x-table.th></x-table.th></x-slot:head>
                    @foreach ($rejection->decisions as $d)
                        <tr wire:key="d-{{ $d->id }}">
                            <x-table.td class="font-mono text-xs">{{ $d->number }}</x-table.td>
                            <x-table.td>{{ $decisions[$d->decision] }}</x-table.td>
                            <x-table.td align="right">@qty($d->quantity)</x-table.td>
                            <x-table.td align="right">@if ($d->amount)@money($d->amount)@endif</x-table.td>
                            <x-table.td class="text-xs">@if ($d->targetPtc)Into {{ $d->targetPtc->number }} · @endif{{ $d->reason }}@if ($d->cancel_reason)<div>Cancelled: {{ $d->cancel_reason }}</div>@endif</x-table.td>
                            <x-table.td><x-status :status="$d->status" /></x-table.td>
                            <x-table.td class="whitespace-nowrap">
                                @if ($d->isDraft()) @can('rejections.approve') <x-button size="sm" variant="secondary" wire:click="act({{ $d->id }}, 'approve')">Approve</x-button> @endcan @endif
                                @if ($d->isApproved() && ! $d->isPosted()) @can('rejections.post') <x-button size="sm" wire:click="act({{ $d->id }}, 'post')" wire:confirm="Post this decision?">Post</x-button> @endcan @endif
                                @if (! $d->isPosted() && ! $d->isCancelled()) @can('rejections.cancel') <x-button size="sm" variant="ghost" wire:click="act({{ $d->id }}, 'cancel')">Cancel</x-button> @endcan @endif
                            </x-table.td>
                        </tr>
                    @endforeach
                </x-table>
                @can('rejections.cancel') <div class="border-t border-zinc-200 p-3 dark:border-zinc-800"><x-input label="Reason for cancelling a decision" wire:model="cancel_reason" class="max-w-md" /></div> @endcan
            </x-card>

            @can('rejections.create')
                <x-card title="Add a decision" :description="'Undecided: '.\App\Support\Format::qty($open).' pieces. One rejection can be split across decisions.'">
                    <form wire:submit="decide" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <x-select label="Decision" wire:model.live="decision" :options="$decisions" />
                        @if ($decision !== 'recover')
                            <x-input label="Quantity" type="number" step="any" min="0" wire:model="quantity" />
                        @else
                            <x-input label="Charge (PKR)" type="number" step="any" min="0" wire:model="amount" :help="$rejection->responsible ? 'Charged to '.$rejection->responsible->name : 'No responsible person recorded'" />
                        @endif
                        @if ($decision === 'rework')
                            <x-select label="Rework in PTC" wire:model.live="target_ptc_id" :options="$targets" placeholder="Choose…" />
                            <x-select label="At stage" wire:model="target_stage_id" :options="$targetStages" placeholder="Choose…" />
                        @endif
                        <x-input label="Reason" wire:model="reason" class="sm:col-span-2" />
                        <div class="flex items-end"><x-button type="submit">Save decision</x-button></div>
                    </form>
                </x-card>
            @endcan
            <x-card title="Attachments (photos)"><livewire:core::shared.attachments :model="$rejection" :key="'a-'.$rejection->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Date" :value="\App\Support\Format::date($rejection->rejection_date)" />
                    <x-people::detail label="Source">@if ($rejection->ptc)<a href="{{ route('production.ptcs.show', $rejection->ptc) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $rejection->ptc->number }}</a>@elseif ($rejection->job)<a href="{{ route('production.jobs.show', $rejection->job) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $rejection->job->number }}</a>@else Store @endif</x-people::detail>
                    <x-people::detail label="Lot"><a href="{{ route('inventory.lots.show', $rejection->lot) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $rejection->lot->lot_number }}</a></x-people::detail>
                    <x-people::detail label="Location" :value="$rejection->location->name" />
                    <x-people::detail label="Found by" :value="$rejection->found_by" />
                    <x-people::detail label="Responsible" :value="$rejection->responsible?->name" />
                    <x-people::detail label="Notes" :value="$rejection->notes" />
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$rejection" :key="'n-'.$rejection->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$rejection" :key="'h-'.$rejection->id" /></x-card>
        </div>
    </div>
</div>
