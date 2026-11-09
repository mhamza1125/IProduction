@props(['color' => 'zinc']) {{-- zinc | green | red | amber | blue --}}

@php
    $colors = [
        'zinc' => 'bg-hover text-muted ring-line-strong/40',
        'green' => 'bg-green-50 text-success ring-green-600/20 dark:bg-green-500/10',
        'red' => 'bg-red-50 text-danger ring-red-600/20 dark:bg-red-500/10',
        'amber' => 'bg-amber-50 text-warning ring-amber-600/20 dark:bg-amber-500/10',
        'blue' => 'bg-blue-50 text-link ring-blue-600/20 dark:bg-blue-500/10',
    ];
@endphp

<span {{ $attributes->merge(['class' => 'inline-flex items-center rounded-md px-2 py-0.5 text-xs font-medium ring-1 ring-inset '.($colors[$color] ?? $colors['zinc'])]) }}>
    {{ $slot }}
</span>
