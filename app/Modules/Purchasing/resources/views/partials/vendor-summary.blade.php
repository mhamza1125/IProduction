{{-- Purchasing totals on the vendor page (PKR values at order rates). --}}
@can('purchase_orders.view')
    @php($summary = app(\App\Modules\Purchasing\Services\VendorSummary::class)->for($vendor))
    <x-card title="Purchasing (PKR)">
        <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
            @foreach ($summary as $label => $value)
                <dt class="text-zinc-500">{{ $label }}</dt><dd class="text-right tabular">@money($value)</dd>
            @endforeach
        </dl>
        <a href="{{ route('purchasing.orders.index', ['q' => $vendor->name]) }}" wire:navigate class="mt-2 inline-block text-sm text-brand-600 hover:underline">Purchase orders</a>
    </x-card>
@endcan
