<div>
    <x-page-header :title="'Order cost · '.$order->number" :description="$order->customer->name.' · direct cost in PKR (overhead such as non-production salaries, rent and utilities is not allocated)'">
        <a href="{{ route('sales.orders.show', $order) }}" wire:navigate class="text-sm text-link hover:underline">Back to the order</a>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-card><p class="text-xs uppercase text-subtle">Actual direct cost</p><p class="mt-1 text-2xl font-semibold tabular">@money($actual, 'PKR')</p></x-card>
        <x-card><p class="text-xs uppercase text-subtle">Planned cost (BOM + route rates)</p><p class="mt-1 text-2xl font-semibold tabular">@money($planned, 'PKR')</p></x-card>
        <x-card><p class="text-xs uppercase text-subtle">Variance (actual − planned)</p><p @class(['mt-1 text-2xl font-semibold tabular', 'text-danger' => (float) \App\Support\Decimal::sub($actual, $planned) > 0])>@money(\App\Support\Decimal::sub($actual, $planned), 'PKR')</p></x-card>
        <x-card><p class="text-xs uppercase text-subtle">Revenue invoiced</p><p class="mt-1 text-2xl font-semibold tabular">@money($revenue, $currency)</p></x-card>
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="By line" :padding="false">
                <x-table>
                    <x-slot:head><x-table.th>Item</x-table.th><x-table.th align="right">Ordered</x-table.th><x-table.th align="right">Delivered (net)</x-table.th><x-table.th align="right">Delivered at cost</x-table.th>
                        <x-table.th align="right">Returns</x-table.th><x-table.th align="right">In production</x-table.th><x-table.th align="right">Actual</x-table.th><x-table.th align="right">Planned</x-table.th><x-table.th align="right">Cost/unit actual · planned</x-table.th></x-slot:head>
                    @foreach ($lines as $l)
                        <tr wire:key="oc-{{ $l['line']->id }}">
                            <x-table.td>{{ $l['line']->variant->fullLabel() }}@if ((float) $l['reserved_cost'])<div class="text-xs text-subtle">reserved stock @money($l['reserved_cost'])</div>@endif</x-table.td>
                            <x-table.td align="right">@qty($l['ordered'])</x-table.td>
                            <x-table.td align="right">@qty($l['net_delivered'])</x-table.td>
                            <x-table.td align="right">@money($l['delivered_cost'])</x-table.td>
                            <x-table.td align="right">@if ((float) $l['return_cost'])−@money($l['return_cost'])@endif</x-table.td>
                            <x-table.td align="right">@money($l['wip'])</x-table.td>
                            <x-table.td align="right" class="font-semibold">@money($l['actual'])</x-table.td>
                            <x-table.td align="right">@money($l['planned'])</x-table.td>
                            <x-table.td align="right">{{ $l['actual_unit'] !== null ? \App\Support\Format::money($l['actual_unit'], null, 4) : '—' }} · {{ $l['planned_unit'] !== null ? \App\Support\Format::money($l['planned_unit'], null, 4) : '—' }}</x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
            <x-card title="Where the cost came from" description="Made and bought supply for this order, by category (PKR).">
                <dl class="grid gap-1 text-sm">
                    @forelse ($categories as $label => $amount)<div class="flex justify-between"><dt>{{ $label }}</dt><dd class="tabular">@money($amount)</dd></div>@empty<p class="text-subtle">No production or purchases recorded for this order yet; delivered goods came from stock.</p>@endforelse
                </dl>
            </x-card>
        </div>
        <div class="space-y-6">
            @if ($canProfit)
                <x-card title="Estimated profit" description="Revenue stays in the customer's currency. Enter the PKR value you expect (or a rate) to estimate profit. This is for reporting only and posts nothing.">
                    @if ($currency !== 'PKR')
                        <div class="grid gap-3">
                            <x-input label="Reference PKR value" type="number" step="any" min="0" wire:model.live.debounce.500ms="reference_pkr" />
                            <x-input :label="'or reference rate (PKR per '.$currency.')'" type="number" step="any" min="0" wire:model.live.debounce.500ms="reference_rate" />
                        </div>
                    @endif
                    <dl class="mt-4 grid gap-1 text-sm">
                        <div class="flex justify-between"><dt>Revenue</dt><dd class="tabular">@money($revenue, $currency)</dd></div>
                        <div class="flex justify-between"><dt>Reference value</dt><dd class="tabular">{{ $reference !== null ? \App\Support\Format::money($reference, 'PKR') : '—' }}</dd></div>
                        <div class="flex justify-between"><dt>Direct cost</dt><dd class="tabular">@money($actual, 'PKR')</dd></div>
                        <div class="flex justify-between border-t border-line pt-1 font-semibold"><dt>Estimated profit</dt><dd @class(['tabular', 'text-danger' => $profit !== null && (float) $profit < 0])>{{ $profit !== null ? \App\Support\Format::money($profit, 'PKR') : '—' }}</dd></div>
                    </dl>
                    <p class="mt-3 text-xs text-subtle">Realized PKR is not matched to orders because conversions are pooled; this stays an estimate.</p>
                </x-card>
            @endif
            <x-card title="Source documents">
                <ul class="space-y-1 text-sm">
                    @forelse ($sources as $s)<li>{{ $s['label'] }} <a href="{{ $s['url'] }}" wire:navigate class="font-mono text-link hover:underline">{{ $s['number'] }}</a></li>@empty<li class="text-subtle">None yet.</li>@endforelse
                </ul>
            </x-card>
        </div>
    </div>
</div>
