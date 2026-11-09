{{--
    <x-select label="Role" wire:model="role" :options="['a' => 'Option A']" placeholder="Choose..." />
    or pass <option> elements in the slot.
--}}
@props(['label' => null, 'help' => null, 'options' => [], 'placeholder' => null, 'required' => false])

@php
    $model = $attributes->wire('model')->value() ?: $attributes->get('name');
    $id = $attributes->get('id') ?? ($model ? 'f-'.str_replace('.', '-', $model) : null);
    $hasError = $model && $errors->has($model);
@endphp

<x-field :label="$label" :for="$id" :error="$model" :help="$help" :required="$required" :class="$attributes->get('class')">
    <select
        id="{{ $id }}"
        @if ($required) required @endif
        {{ $attributes->except('class')->class([
            'block w-full rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm text-fg shadow-sm ring-1 ring-inset focus:ring-2 focus:ring-inset focus:ring-brand-600',
            'ring-danger' => $hasError,
            'ring-line-strong' => ! $hasError,
        ]) }}
    >
        @if ($placeholder !== null)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}">{{ $text }}</option>
        @endforeach
        {{ $slot }}
    </select>
</x-field>
