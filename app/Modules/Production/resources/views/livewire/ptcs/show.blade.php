@php($unit = $ptc->variant->item->stockUnit->symbol)
<div x-data="{ ask: null }">
    <x-page-header :title="'PTC '.$ptc->number" :description="$ptc->variant->fullLabel().' · planned '.\App\Support\Format::qty($ptc->planned_qty).' '.$unit.($ptc->orderLine ? ' · '.$ptc->orderLine->order->number.' '.$ptc->orderLine->order->customer->name : ' · for stock')">
        <x-status :status="$ptc->status" />
    </x-page-header>

    <div class="mb-6 flex flex-wrap items-center gap-2">
        @if ($ptc->isDraft())
            @can('ptcs.edit') <x-button variant="secondary" :href="route('production.ptcs.edit', $ptc)" icon="pencil">Edit</x-button> @endcan
            @can('ptcs.approve') <x-button wire:click="approve" wire:confirm="Approve {{ $ptc->number }}? This copies the route and BOM.">Approve</x-button> @endcan
            @can('ptcs.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan
        @endif
        @if (in_array($ptc->status, ['approved', 'in_progress', 'on_hold'], true))
            @can('ptcs.edit')
                @if ($ptc->status === 'on_hold')
                    <x-button variant="secondary" wire:click="status('resume')">Resume</x-button>
                @else
                    <x-button variant="ghost" x-on:click="ask = 'hold'">Put on hold</x-button>
                @endif
                <x-button wire:click="status('complete')" wire:confirm="Complete {{ $ptc->number }}? Material still held is counted as used.">Complete</x-button>
                <x-button variant="ghost" x-on:click="ask = 'close_early'">Close early</x-button>
            @endcan
            @can('ptcs.override') @if ($ptc->children->isNotEmpty()) <x-button variant="ghost" x-on:click="ask = 'override_complete'">Complete with open children</x-button> @endif @endcan
        @endif
        @if (! $ptc->isFinished() && $transactions->isEmpty() && ! $ptc->isDraft())
            @can('ptcs.cancel') <x-button variant="ghost" x-on:click="ask = 'cancel'">Cancel</x-button> @endcan
        @endif
        @if (! $ptc->isDraft())
            @can('ptcs.print') <a href="{{ route('production.ptcs.print', $ptc) }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"><x-icon name="printer" class="size-4" /> PTC card</a> @endcan
        @endif
        <div x-show="ask" x-cloak class="mt-2 flex w-full flex-wrap items-end gap-2 rounded-md bg-zinc-50 p-3 dark:bg-zinc-800/60">
            <x-input label="Reason (say what happens to remaining stock when closing early)" wire:model="reason" class="min-w-64 flex-1" />
            <x-button x-show="ask === 'hold'" wire:click="status('hold')" x-on:click="ask = null">Put on hold</x-button>
            <x-button x-show="ask === 'cancel'" variant="danger" wire:click="status('cancel')" x-on:click="ask = null">Cancel PTC</x-button>
            <x-button x-show="ask === 'close_early'" variant="danger" wire:click="status('close_early')" x-on:click="ask = null">Close early</x-button>
            <x-button x-show="ask === 'override_complete'" variant="danger" wire:click="status('override_complete')" x-on:click="ask = null">Complete anyway</x-button>
            <x-button variant="ghost" x-on:click="ask = null">Close</x-button>
        </div>
    </div>

    @if (! $ptc->isDraft())
        <x-card title="Stage board" :padding="false" class="mb-6">
            <x-table>
                <x-slot:head>
                    <x-table.th>Stage</x-table.th><x-table.th>Work</x-table.th><x-table.th align="right">Issued</x-table.th><x-table.th align="right">Received</x-table.th>
                    <x-table.th align="right">Rejected</x-table.th><x-table.th align="right">Pending (with workers)</x-table.th><x-table.th align="right">Available</x-table.th><x-table.th>Who has it</x-table.th>
                </x-slot:head>
                @foreach ($board as $row)
                    @php($s = $row['stage'])
                    <tr wire:key="st-{{ $s->id }}" @class(['opacity-50' => $s->is_skipped])>
                        <x-table.td class="font-medium">{{ $s->sequence }}. {{ $s->stage->name }} @if ($s->is_optional)<x-badge color="zinc">optional</x-badge>@endif @if ($s->is_skipped)<div class="text-xs text-zinc-500">Skipped: {{ $s->skip_reason }}</div>@endif</x-table.td>
                        <x-table.td class="text-xs">{{ ucfirst($s->work_type) }}@if ($s->machine) · {{ $s->machine }}@endif @can('cost.view')@if ($s->wage_rate)<div>wage @money($s->wage_rate, null, 4)</div>@endif @if ($s->contractor_rate)<div>contractor @money($s->contractor_rate, null, 4)</div>@endif @endcan</x-table.td>
                        <x-table.td align="right">@qty($s->issued_qty)</x-table.td>
                        <x-table.td align="right">@qty($s->received_qty)</x-table.td>
                        <x-table.td align="right" @class(['text-red-600' => (float) $row['rejected'] > 0])>@qty($s->rejected_qty)</x-table.td>
                        <x-table.td align="right">@qty($row['in_production'])</x-table.td>
                        <x-table.td align="right" class="font-semibold">@qty($row['available'])</x-table.td>
                        <x-table.td class="text-xs">@foreach (collect($holders)->where('stage_id', $s->stage_id) as $h)<div>{{ $h['worker'] }}: @qty($h['pending'])</div>@endforeach</x-table.td>
                    </tr>
                @endforeach
                <tr><x-table.td colspan="8" class="text-sm">Released to store: <span class="font-semibold">@qty($ptc->released_qty) {{ $unit }}</span>@if ((float) $ptc->merged_out_qty) · moved to other PTCs: @qty($ptc->merged_out_qty)@endif @if ($ptc->outputLot) · output lot <a href="{{ route('inventory.lots.show', $ptc->outputLot) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $ptc->outputLot->lot_number }}</a>@endif</x-table.td></tr>
            </x-table>
        </x-card>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($ptc->isActive())
                @can('production_transactions.create')
                    <x-card title="Record a transaction">
                        <form wire:submit="record" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                            <x-select label="Transaction" wire:model.live="type" :options="['material_issue' => 'Material Issue', 'extra_issue' => 'Extra Issue (reason)', 'stage_issue' => 'Stage Issue', 'stage_receive' => 'Stage Receive', 'reject' => 'Reject at stage', 'release' => 'Release to store', 'skip' => 'Skip optional stage']" />
                            @if ($type !== 'release')
                                <x-select label="Stage" wire:model="ptc_stage_id" :options="$stages->mapWithKeys(fn ($s) => [$s->id => $s->sequence.'. '.$s->stage->name])" :placeholder="$type === 'material_issue' ? 'Any' : 'Choose…'" />
                            @endif
                            @if (in_array($type, ['material_issue', 'extra_issue', 'stage_issue', 'stage_receive'], true))
                                <x-select label="Worker" wire:model.live="worker_type" :options="['employee' => 'Employee', 'contractor' => 'Contractor']" />
                                @if ($worker_type === 'employee')
                                    <x-select label="Employee" wire:model="employee_id" :options="$employees" placeholder="Choose…" />
                                @else
                                    <x-select label="Contractor" wire:model="contractor_id" :options="$contractors" placeholder="Choose…" />
                                @endif
                            @endif
                            @if (in_array($type, ['material_issue', 'extra_issue'], true))
                                <x-select label="Material" wire:model="material_variant_id" :options="$materialOptions" placeholder="Choose…" class="lg:col-span-2" />
                            @endif
                            @if ($type === 'stage_receive')
                                <x-input label="Good" type="number" step="any" min="0" wire:model="good" />
                                <x-input label="Rejected" type="number" step="any" min="0" wire:model="rejected" />
                                <x-input label="Over-output reason (needs approval)" wire:model="over_reason" />
                            @elseif ($type !== 'skip')
                                <x-input label="Quantity" type="number" step="any" min="0" wire:model="quantity" />
                            @endif
                            @if ($type === 'stage_issue')
                                <x-checkbox wire:model="from_stock" label="Take from stock (first stage)" />
                            @endif
                            @if (in_array($type, ['extra_issue', 'stage_receive', 'reject', 'skip'], true))
                                <x-input :label="$type === 'stage_receive' ? 'Rejection reason' : 'Reason'" wire:model="tx_reason" class="lg:col-span-2" />
                            @endif
                            <x-date-input label="Date" wire:model="date" />
                            <div class="flex items-end"><x-button type="submit">Record</x-button></div>
                        </form>
                    </x-card>
                @endcan
            @endif

            @if (! $ptc->isDraft())
                <x-card title="Material" description="Issued = consumed + returned + wastage + still held. Expected use is the BOM for the pieces produced." :padding="false">
                    <x-table empty="No material on this PTC.">
                        <x-slot:head><x-table.th>Material</x-table.th><x-table.th align="right">Expected</x-table.th><x-table.th align="right">Issued</x-table.th><x-table.th align="right">Extra</x-table.th><x-table.th align="right">Consumed</x-table.th><x-table.th align="right">Returned</x-table.th><x-table.th align="right">Wastage</x-table.th><x-table.th align="right">Held</x-table.th><x-table.th align="right">Variance</x-table.th></x-slot:head>
                        @foreach ($reconciliation as $r)
                            <tr wire:key="rc-{{ $r['variant_id'] }}">
                                <x-table.td>{{ $variantNames[$r['variant_id']]?->fullLabel() }}</x-table.td>
                                <x-table.td align="right">@qty($r['expected'])</x-table.td><x-table.td align="right">@qty($r['issued'])</x-table.td><x-table.td align="right">@qty($r['extra'])</x-table.td>
                                <x-table.td align="right">@qty($r['consumed'])</x-table.td><x-table.td align="right">@qty($r['returned'])</x-table.td><x-table.td align="right">@qty($r['wastage'])</x-table.td>
                                <x-table.td align="right" class="font-semibold">@qty($r['held'])</x-table.td>
                                <x-table.td align="right" @class(['text-red-600' => (float) $r['variance'] > 0])>@qty($r['variance'])</x-table.td>
                            </tr>
                        @endforeach
                    </x-table>
                    @if ($heldMaterial->isNotEmpty() && $ptc->isActive())
                        @can('production_transactions.create')
                            <div class="border-t border-zinc-200 p-4 dark:border-zinc-800">
                                <h3 class="mb-2 text-sm font-semibold">Return unused material or record wastage</h3>
                                <div class="space-y-2">
                                    @foreach ($heldMaterial as $key => $h)
                                        <div wire:key="h-{{ $key }}" class="flex flex-wrap items-end gap-2 text-sm">
                                            <span class="min-w-48">{{ $variantNames[$h['item_variant_id']]?->fullLabel() }} · lot {{ $h['lot_id'] }} · held @qty($h['quantity'])</span>
                                            <input type="number" step="any" min="0" wire:model="held.{{ $key }}" aria-label="Quantity" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                                            <x-button size="sm" variant="secondary" wire:click="returnHeld('{{ $key }}', 'return')">Return to store</x-button>
                                            <x-button size="sm" variant="ghost" wire:click="returnHeld('{{ $key }}', 'waste')">Wastage</x-button>
                                            @error("held.$key") <span class="text-xs text-red-600">{{ $message }}</span> @enderror
                                        </div>
                                    @endforeach
                                    <x-input label="Reason (required for wastage)" wire:model="tx_reason" class="max-w-md" />
                                </div>
                            </div>
                        @endcan
                    @endif
                </x-card>
            @endif

            <x-card title="Timeline" :padding="false">
                <x-table empty="No transactions yet.">
                    <x-slot:head><x-table.th>Date</x-table.th><x-table.th>Transaction</x-table.th><x-table.th>Stage</x-table.th><x-table.th>Worker</x-table.th><x-table.th>Item / lot</x-table.th><x-table.th align="right">Qty</x-table.th>@can('cost.view')<x-table.th align="right">Amount</x-table.th>@endcan<x-table.th>Reason · by</x-table.th></x-slot:head>
                    @foreach ($transactions as $t)
                        <tr wire:key="tx-{{ $t->id }}">
                            <x-table.td>@date($t->transaction_date)</x-table.td>
                            <x-table.td class="font-medium">{{ $t->label() }}</x-table.td>
                            <x-table.td>{{ $t->ptcStage?->stage->name }}</x-table.td>
                            <x-table.td>{{ $t->workerName() }}</x-table.td>
                            <x-table.td class="text-xs">{{ $t->variant->sku }}@if ($t->lot) · {{ $t->lot->lot_number }}@endif</x-table.td>
                            <x-table.td align="right">@qty($t->quantity)</x-table.td>
                            @can('cost.view')<x-table.td align="right">@if ($t->amount !== null)@money($t->amount)@endif</x-table.td>@endcan
                            <x-table.td class="text-xs">{{ $t->reason }} · {{ $t->creator?->name }}</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$ptc" :key="'a-'.$ptc->id" /></x-card>
        </div>

        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    <x-people::detail label="Route" :value="$ptc->route->name" />
                    <x-people::detail label="Stages" :value="($ptc->startStage?->name ?? 'first').' → '.($ptc->endStage?->name ?? 'last')" />
                    <x-people::detail label="Order line">@if ($ptc->orderLine)<a href="{{ route('sales.orders.show', $ptc->orderLine->sales_order_id) }}" wire:navigate class="text-brand-600 hover:underline">{{ $ptc->orderLine->order->number }}</a>@else For stock @endif</x-people::detail>
                    @if ($ptc->parent) <x-people::detail label="Parent PTC"><a href="{{ route('production.ptcs.show', $ptc->parent) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $ptc->parent->number }}</a> · @qty($ptc->parent_qty)</x-people::detail> @endif
                    <x-people::detail label="Responsible" :value="$ptc->responsible?->name" />
                    <x-people::detail label="Start · target" :value="\App\Support\Format::date($ptc->start_date).' · '.\App\Support\Format::date($ptc->target_date)" />
                    <x-people::detail label="Notes" :value="$ptc->notes" />
                    @if ($ptc->close_reason) <x-people::detail label="Close reason" :value="$ptc->close_reason" /> @endif
                    @if ($ptc->cancel_reason) <x-people::detail label="Cancel reason" :value="$ptc->cancel_reason" /> @endif
                </dl>
            </x-card>

            @if ($materials->isNotEmpty())
                <x-card title="BOM copy (per piece)">
                    <ul class="space-y-1 text-sm">@foreach ($materials as $m)<li>{{ $m->variant->fullLabel() }}: {{ rtrim(rtrim($m->per_unit, '0'), '.') }} @if ((float) $m->wastage_percent)<span class="text-xs text-zinc-500">incl. {{ $m->wastage_percent }}% wastage</span>@endif</li>@endforeach</ul>
                </x-card>
            @endif

            @can('cost.view')
                @if ($costs !== null && ! $ptc->isDraft())
                    <x-card title="Cost so far (PKR)">
                        <dl class="grid gap-1 text-sm">
                            @foreach ($costs as $cat => $amount)<div class="flex justify-between"><dt>{{ $costCategories[$cat] ?? $cat }}</dt><dd class="tabular">@money($amount)</dd></div>@endforeach
                            <div class="flex justify-between border-t border-zinc-200 pt-1 font-semibold dark:border-zinc-800"><dt>Total charged</dt><dd class="tabular">@money($total)</dd></div>
                            <div class="flex justify-between"><dt>Not yet released</dt><dd class="tabular">@money($pool)</dd></div>
                            @if ($ptc->outputLot?->unit_cost)<div class="flex justify-between"><dt>Cost per released piece</dt><dd class="tabular">@money($ptc->outputLot->unit_cost, null, 4)</dd></div>@endif
                        </dl>
                    </x-card>
                @endif
            @endcan

            <x-card title="Children and merges">
                <ul class="mb-3 space-y-1 text-sm">
                    @foreach ($ptc->children as $c)<li>Child <a href="{{ route('production.ptcs.show', $c) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $c->number }}</a> · @qty($c->planned_qty) <x-status :status="$c->status" /></li>@endforeach
                    @foreach ($links->where('type', '!=', 'child') as $l)<li class="text-xs">{{ ucfirst($l->type) }}: {{ $l->source->number }} → {{ $l->ptc->number }} · @qty($l->quantity) at {{ $l->stage?->name }}</li>@endforeach
                    @if ($ptc->children->isEmpty() && $links->where('type', '!=', 'child')->isEmpty())<li class="text-zinc-500">None.</li>@endif
                </ul>
                @if ($ptc->isActive())
                    @can('ptcs.create')
                        <form wire:submit="createChild" class="mb-4 grid gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                            <h3 class="text-sm font-semibold">New child PTC</h3>
                            <x-input label="Quantity" type="number" step="any" min="0" wire:model="child_qty" />
                            <x-select label="Start stage" wire:model="child_stage_id" :options="$stages->mapWithKeys(fn ($s) => [$s->stage_id => $s->stage->name])" placeholder="Same as this PTC" />
                            <x-button type="submit" size="sm" variant="secondary">Create child</x-button>
                        </form>
                    @endcan
                    @can('ptcs.edit')
                        <div class="grid gap-2 border-t border-zinc-200 pt-3 dark:border-zinc-800">
                            <h3 class="text-sm font-semibold">Move waiting pieces</h3>
                            <x-select label="To PTC (same order, variant and route)" wire:model="target_ptc_id" :options="$mergeTargets" placeholder="Choose…" />
                            <x-select label="At stage" wire:model="move_stage_id" :options="$stages->mapWithKeys(fn ($s) => [$s->stage_id => $s->stage->name])" placeholder="Choose…" />
                            <x-input label="Quantity" type="number" step="any" min="0" wire:model="move_qty" />
                            <div class="flex gap-2">
                                <x-button size="sm" variant="secondary" wire:click="move('merge')" wire:confirm="Merge these pieces into the other PTC?">Merge</x-button>
                                <x-button size="sm" variant="ghost" wire:click="move('split')">Move to child</x-button>
                            </div>
                        </div>
                    @endcan
                @endif
            </x-card>

            <x-card title="Notes"><livewire:core::shared.notes :model="$ptc" :key="'n-'.$ptc->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$ptc" :key="'h-'.$ptc->id" /></x-card>
        </div>
    </div>
</div>
