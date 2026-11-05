@props(['label' => null, 'help' => null, 'required' => false, 'rows' => 3])

@php
    $model = $attributes->wire('model')->value() ?: $attributes->get('name');
    $id = $attributes->get('id') ?? ($model ? 'f-'.str_replace('.', '-', $model) : null);
    $hasError = $model && $errors->has($model);
@endphp

<x-field :label="$label" :for="$id" :error="$model" :help="$help" :required="$required" :class="$attributes->get('class')">
    <textarea
        id="{{ $id }}"
        rows="{{ $rows }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-md border-0 bg-white px-3 py-2 text-sm text-zinc-900 shadow-sm ring-1 ring-inset placeholder:text-zinc-400 focus:ring-2 focus:ring-inset focus:ring-brand-600 dark:bg-zinc-900 dark:text-zinc-100',
            'ring-red-500' => $hasError,
            'ring-zinc-300 dark:ring-zinc-700' => ! $hasError,
        ]) }}
    >{{ $slot }}</textarea>
</x-field>
