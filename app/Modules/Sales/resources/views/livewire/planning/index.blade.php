<div>
    <x-page-header title="Planning" description="Open order lines: reserve free stock, decide how to supply the rest, check materials and create purchase orders. Rejected and returned stock never counts; open POs count as incoming, not as stock." />

    <x-card :padding="false" class="mb-6">
        <div class="flex flex-wrap gap-3 border-b border-line px-4 py-3">
            <select wire:model.live="customer" aria-label="Customer" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All customers</option>@foreach ($customers as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach
            </select>
            @if ($orderId) <x-button size="sm" variant="ghost" wire:click="$set('orderId', null)">Show all orders</x-button> @endif
        </div>
        <x-table empty="No open order lines.">
            <x-slot:head>
                <x-table.th>Order</x-table.th><x-table.th>Customer</x-table.th><x-table.th>Item</x-table.th><x-table.th>Due</x-table.th>
                <x-table.th align="right">Required</x-table.th><x-table.th align="right">Free</x-table.th><x-table.th align="right">Reserved</x-table.th>
                <x-table.th align="right">On order</x-table.th><x-table.th align="right">In production</x-table.th><x-table.th align="right">Shortfall</x-table.th><x-table.th>Blocking · next action</x-table.th>
            </x-slot:head>
            @foreach ($rows as $r)
                @php($l = $r['line'])
                <tr wire:key="pl-{{ $l->id }}" wire:click="select({{ $l->id }})" @class(['cursor-pointer hover:bg-hover', 'bg-selected' => $lineId === $l->id])>
                    <x-table.td class="font-mono text-xs">{{ $l->order->number }}</x-table.td>
                    <x-table.td>{{ $l->order->customer->name }}</x-table.td>
                    <x-table.td wide>{{ $l->variant->fullLabel() }}</x-table.td>
                    <x-table.td @class(['text-danger' => ($l->due_date ?? $l->order->due_date)?->isPast()])>@date($l->due_date ?? $l->order->due_date)</x-table.td>
                    <x-table.td align="right">@qty($r['required'])</x-table.td>
                    <x-table.td align="right">@qty($r['free'])</x-table.td>
                    <x-table.td align="right">@qty($r['reserved'])</x-table.td>
                    <x-table.td align="right">@qty($r['on_order'])</x-table.td>
                    <x-table.td align="right">@qty($r['in_production'])</x-table.td>
                    <x-table.td align="right" @class(['font-semibold', 'text-danger' => (float) $r['shortfall'] > 0])>@qty($r['shortfall'])</x-table.td>
                    <x-table.td class="text-xs">{{ $r['blocker'] }} → <span class="font-medium">{{ $r['next'] }}</span></x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    @if ($line && $picture)
        <div class="grid gap-6 xl:grid-cols-2">
            <x-card :title="$line->order->number.' · '.$line->variant->fullLabel()" :description="'Required '.\App\Support\Format::qty($picture['required']).' · reserved '.\App\Support\Format::qty($picture['reserved']).' · free in stock '.\App\Support\Format::qty($picture['free'])">
                <div class="space-y-5">
                    <div>
                        <h3 class="text-sm font-semibold">1. Reserve free stock</h3>
                        @can('reservations.create')
                            <x-button size="sm" class="mt-2" wire:click="reserve" :disabled="(float) $picture['free'] <= 0 || \App\Support\Decimal::compare($picture['reserved'], $picture['required']) >= 0">Reserve available free stock</x-button>
                        @endcan
                    </div>
                    <form wire:submit="savePlan">
                        <h3 class="text-sm font-semibold">2. Split the rest ({{ \App\Support\Format::qty(\App\Support\Decimal::sub($picture['required'], $picture['reserved'], 4)) }} not covered by stock)</h3>
                        <div class="mt-2 grid gap-3 sm:grid-cols-4">
                            <x-input label="Buy" type="number" step="any" min="0" wire:model="buy" />
                            <x-input label="Make in-house" type="number" step="any" min="0" wire:model="make" />
                            <x-input label="Contractor" type="number" step="any" min="0" wire:model="contractor" />
                            <x-select label="Route (make)" wire:model="route_id" :options="$routes" placeholder="—" />
                        </div>
                        <div class="mt-2 flex flex-wrap gap-2">
                            @can('planning.create') <x-button type="submit" size="sm" variant="secondary">Save plan</x-button> @endcan
                            @if ((float) $line->plan_make > 0 && \Illuminate\Support\Facades\Route::has('production.ptcs.create'))
                                @can('ptcs.create') <x-button size="sm" :href="route('production.ptcs.create', ['line' => $line->id])">Create PTC for {{ \App\Support\Format::qty($line->plan_make) }}</x-button> @endcan
                            @endif
                            @if ((float) $line->plan_contractor > 0 && \Illuminate\Support\Facades\Route::has('production.jobs.create'))
                                @can('contractor_jobs.create') <x-button size="sm" variant="ghost" :href="route('production.jobs.create')">New contractor job</x-button> @endcan
                            @endif
                        </div>
                    </form>
                </div>
            </x-card>

            <x-card title="3. Materials for make + contractor" description="Required includes wastage. Free stock is used first, then incoming purchase orders; the rest is short.">
                @if ($requirementsError)
                    <p class="text-sm text-danger">{{ $requirementsError }}</p>
                @elseif ($requirements === [])
                    <p class="text-sm text-subtle">Nothing planned to make yet.</p>
                @else
                    <table class="min-w-full text-sm">
                        <thead class="text-xs uppercase text-subtle"><tr><th class="py-1 text-left">Component</th><th class="py-1 text-right">Required</th><th class="py-1 text-right">Free</th><th class="py-1 text-right">Incoming</th><th class="py-1 text-right">Short</th></tr></thead>
                        <tbody class="divide-y divide-line">
                            @foreach ($requirements as $r)
                                <tr>
                                    <td class="py-1" style="padding-left: {{ ($r['level'] - 1) * 1.25 }}rem">{{ $r['variant']->fullLabel() }} @if ($r['made'])<x-badge color="blue">made</x-badge>@endif</td>
                                    <td class="py-1 text-right tabular">@qty($r['required'])</td>
                                    <td class="py-1 text-right tabular">@qty($r['free'])</td>
                                    <td class="py-1 text-right tabular">@qty($r['incoming'])</td>
                                    <td @class(['py-1 text-right tabular font-semibold', 'text-danger' => (float) $r['shortage'] > 0])>@qty($r['shortage'])</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif
            </x-card>

            @if ($purchaseLabels->isNotEmpty())
                <x-card title="4. Purchase orders" description="One draft purchase order per vendor, linked to this order line." class="xl:col-span-2" :padding="false">
                    <form wire:submit="createPurchaseOrders">
                        <x-table>
                            <x-slot:head><x-table.th>Order</x-table.th><x-table.th>Item</x-table.th><x-table.th align="right">Quantity</x-table.th><x-table.th>Vendor</x-table.th><x-table.th align="right">Rate (PKR)</x-table.th></x-slot:head>
                            @foreach ($purchaseLabels as $variantId => $label)
                                <tr wire:key="pp-{{ $variantId }}">
                                    <x-table.td><input type="checkbox" wire:model="purchase.{{ $variantId }}.order" aria-label="Order {{ $label }}" class="size-4 rounded border-line-strong bg-surface text-brand-600"></x-table.td>
                                    <x-table.td>{{ $label }}</x-table.td>
                                    <x-table.td align="right"><input type="number" step="any" min="0" wire:model="purchase.{{ $variantId }}.quantity" aria-label="Quantity" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></x-table.td>
                                    <x-table.td>
                                        <select wire:model="purchase.{{ $variantId }}.vendor_id" aria-label="Vendor" class="w-64 rounded-md border-0 py-1.5 text-sm ring-1 ring-line-strong bg-surface"><option value="">Choose…</option>@foreach ($vendors as $id => $n)<option value="{{ $id }}">{{ $n }}</option>@endforeach</select>
                                        @error("purchase.$variantId.vendor_id") <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
                                    </x-table.td>
                                    <x-table.td align="right"><input type="number" step="any" min="0" wire:model="purchase.{{ $variantId }}.rate" aria-label="Rate" class="w-28 rounded-md border-0 py-1.5 text-right text-sm ring-1 ring-line-strong bg-surface"></x-table.td>
                                </tr>
                            @endforeach
                        </x-table>
                        @can('purchase_orders.create')
                            <div class="flex justify-end border-t border-line p-3"><x-button type="submit">Create draft purchase orders</x-button></div>
                        @endcan
                    </form>
                </x-card>
            @endif
        </div>
    @endif
</div>
