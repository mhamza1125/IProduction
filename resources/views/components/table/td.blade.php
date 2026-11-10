@props(['align' => 'left', 'wide' => false])

{{-- Numbers never wrap; text columns keep a readable minimum width (wide: for item
     names), so a wide table scrolls inside its card instead of breaking item names
     word by word and dates at their hyphens. --}}
<td {{ $attributes->merge(['class' => 'px-4 py-2.5 text-muted '.match ($align) {
    'right' => 'text-right tabular whitespace-nowrap',
    'center' => 'text-center whitespace-nowrap',
    default => $wide ? 'min-w-60' : 'min-w-28',
}]) }}>
    {{ $slot }}
</td>
