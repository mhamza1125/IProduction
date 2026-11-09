{{--
    <x-input label="Name" wire:model="name" required />
    The validation error key is taken from wire:model (or name) automatically.
--}}
@props(['label' => null, 'help' => null, 'type' => 'text', 'required' => false])

@php
    $model = $attributes->wire('model')->value() ?: $attributes->get('name');
    $id = $attributes->get('id') ?? ($model ? 'f-'.str_replace('.', '-', $model) : null);
    $hasError = $model && $errors->has($model);
@endphp

<x-field :label="$label" :for="$id" :error="$model" :help="$help" :required="$required" :class="$attributes->get('class')">
    <input
        type="{{ $type }}"
        id="{{ $id }}"
        @if ($required) required @endif
        @if ($hasError) aria-invalid="true" @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-md border-0 bg-surface px-3 py-2 text-sm text-fg shadow-sm ring-1 ring-inset placeholder:text-subtle focus:ring-2 focus:ring-inset focus:ring-brand-600 disabled:bg-hover disabled:text-subtle',
            'ring-danger' => $hasError,
            'ring-line-strong' => ! $hasError,
            'text-right tabular' => $type === 'number',
        ]) }}
    />
</x-field>
