@php($r = $return)
<div>
    <x-page-header :title="'Delivery return '.$r->number" :description="$r->customer->name.' · '.$r->deliveryLine->variant->fullLabel().' · '.\App\Support\Format::qty($r->quantity).' · '.$r->reason" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-document-actions :document="$r" :edit-route="route('delivery.returns.edit', $r)" :print-route="$r->isDraft() ? null : route('delivery.returns.print', $r)" />
        @if ($r->isDraft()) @can('delivery_returns.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan @endif
        @if ($r->isPosted()) @can('credit_notes.create') <x-button variant="secondary" :href="route('delivery.credit-notes.create', ['return' => $r->id])">Credit note</x-button> @endcan @endif
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($r->isPosted())
                <x-card title="Inspection" :description="'Waiting: '.\App\Support\Format::qty($r->uninspected()).' · restocked '.\App\Support\Format::qty($r->restocked_qty).' · rejected '.\App\Support\Format::qty($r->rejected_qty)">
                    @if ((float) $r->uninspected() > 0)
                        @can('delivery_returns.post')
                            <form wire:submit="inspect" class="grid gap-4 sm:grid-cols-4">
                                <x-select label="Decision" wire:model.live="decision" :options="['restock' => 'Restock to Available', 'reject' => 'Rework, scrap or sell (to Rejections)']" class="sm:col-span-2" />
                                <x-input label="Quantity" type="number" step="any" min="0" wire:model="quantity" />
                                @if ($decision === 'restock')
                                    <x-select label="At stage" wire:model="stage_id" :options="$stages" placeholder="Finished goods" />
                                @else
                                    <x-input label="Reason" wire:model="inspect_reason" />
                                @endif
                                <div class="flex items-end"><x-button type="submit">Record</x-button></div>
                            </form>
                        @endcan
                    @else
                        <p class="text-sm text-zinc-500">All returned pieces have been inspected.</p>
                    @endif
                    @if ($rejections->isNotEmpty())
                        <ul class="mt-4 space-y-1 text-sm">@foreach ($rejections as $rj)<li>Rejection <a href="{{ route('production.rejections.show', $rj) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $rj->number }}</a> · @qty($rj->quantity) <x-status :status="$rj->status" /></li>@endforeach</ul>
                    @endif
                </x-card>
            @endif
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$r" :key="'a-'.$r->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Delivery"><a href="{{ route('delivery.deliveries.show', $r->deliveryLine->delivery) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $r->deliveryLine->delivery->number }}</a> · {{ $r->deliveryLine->orderLine->order->number }}</x-people::detail>
                    @if ($r->invoiceLine) <x-people::detail label="Invoice"><a href="{{ route('delivery.invoices.show', $r->invoiceLine->invoice) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $r->invoiceLine->invoice->number }}</a></x-people::detail> @endif
                    <x-people::detail label="Lot"><a href="{{ route('inventory.lots.show', $r->lot) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $r->lot->lot_number }}</a></x-people::detail>
                    <x-people::detail label="Return date" :value="\App\Support\Format::date($r->return_date)" />
                    <x-people::detail label="Replacement" :value="$r->replacement_required ? 'Required: order line reopened' : 'Not required'" />
                    @can('cost.view') @if ($r->unit_cost !== null) <x-people::detail label="Unit cost (PKR)" :value="\App\Support\Format::money($r->unit_cost, null, 4)" /> @endif @endcan
                    <x-people::detail label="Notes" :value="$r->notes" />
                    @foreach ($credits as $c) <x-people::detail label="Credit note"><a href="{{ route('delivery.credit-notes.show', $c) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $c->number }}</a></x-people::detail> @endforeach
                </dl>
            </x-card>
            <x-card title="Notes"><livewire:core::shared.notes :model="$r" :key="'n-'.$r->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$r" :key="'h-'.$r->id" /></x-card>
        </div>
    </div>
</div>
