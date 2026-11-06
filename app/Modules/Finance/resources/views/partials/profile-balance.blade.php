{{-- Current balance and ledger link on customer / vendor / employee / contractor pages. --}}
@php
    $ledgerType = match (true) {
        $profile instanceof \App\Modules\People\Models\Customer => 'customer',
        $profile instanceof \App\Modules\People\Models\Vendor => 'vendor',
        $profile instanceof \App\Modules\People\Models\Employee => 'employee',
        $profile instanceof \App\Modules\People\Models\Contractor => 'contractor',
        default => null,
    };
    $currency = $profile->currency_code ?? 'PKR';
@endphp
@if ($ledgerType && auth()->user()->can('ledgers.view'))
    @php($balance = app(\App\Modules\Finance\Services\Ledgers::class)->partyBalance($profile))
    <x-card title="Balance">
        <p class="text-2xl font-semibold tabular">@money(ltrim($balance, '-'), $currency)</p>
        <p class="text-xs text-zinc-500">
            @if (\App\Support\Decimal::isZero($balance)) Settled
            @elseif (\App\Support\Decimal::isNegative($balance)) We owe them (credit)
            @else They owe us (debit)
            @endif
        </p>
        <a href="{{ route('finance.ledgers', ['type' => $ledgerType, 'id' => $profile->id]) }}" wire:navigate class="mt-2 inline-block text-sm text-brand-600 hover:underline">Open ledger</a>
    </x-card>
@endif
