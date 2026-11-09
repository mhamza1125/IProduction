@props(['label' => null, 'help' => null])

@php
    $model = $attributes->wire('model')->value() ?: $attributes->get('name');
    $id = $attributes->get('id') ?? ($model ? 'f-'.str_replace('.', '-', $model).'-'.md5((string) $attributes->get('value')) : null);
@endphp

<div {{ $attributes->only('class')->merge(['class' => 'flex items-start gap-2']) }}>
    <input
        type="checkbox"
        id="{{ $id }}"
        {{ $attributes->except('class')->merge(['class' => 'mt-0.5 size-4 rounded border-line-strong bg-surface text-brand-600 focus:ring-brand-600']) }}
    />
    @if ($label)
        <div class="text-sm">
            <label for="{{ $id }}" class="font-medium text-muted">{{ $label }}</label>
            @if ($help)
                <p class="text-xs text-subtle">{{ $help }}</p>
            @endif
        </div>
    @endif
</div>
