@props(['align' => 'left'])

<td {{ $attributes->merge(['class' => 'px-4 py-2.5 text-zinc-700 dark:text-zinc-300 '.($align === 'right' ? 'text-right tabular' : ($align === 'center' ? 'text-center' : ''))]) }}>
    {{ $slot }}
</td>
