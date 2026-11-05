{{-- One label/value pair in a details grid. Empty values show a dash. --}}
@props(['label', 'value' => null])

<div {{ $attributes->merge(['class' => 'min-w-0']) }}>
    <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $label }}</dt>
    <dd class="mt-0.5 whitespace-pre-line break-words text-sm text-zinc-900 dark:text-zinc-100">@if (trim($slot) !== ''){{ $slot }}@else{{ filled($value) ? trim((string) $value) : '—' }}@endif</dd>
</div>
