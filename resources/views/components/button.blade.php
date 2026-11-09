@props([
    'variant' => 'primary',   // primary | secondary | danger | ghost
    'size' => 'md',           // sm | md
    'href' => null,
    'icon' => null,
    'type' => 'button',
])

@php
    $base = 'inline-flex items-center justify-center gap-1.5 rounded-md font-medium transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500 focus-visible:ring-offset-2 focus-visible:ring-offset-surface disabled:opacity-50 disabled:pointer-events-none';
    $sizes = [
        'sm' => 'px-2.5 py-1.5 text-xs',
        'md' => 'px-3.5 py-2 text-sm',
    ];
    $variants = [
        'primary' => 'bg-primary text-white shadow-sm hover:bg-primary-hover',
        'secondary' => 'bg-raised text-fg shadow-sm ring-1 ring-inset ring-line-strong/60 hover:bg-hover',
        'danger' => 'bg-red-600 text-white shadow-sm hover:bg-red-700',
        'ghost' => 'text-muted hover:bg-hover hover:text-fg',
    ];
    $classes = $base.' '.($sizes[$size] ?? $sizes['md']).' '.($variants[$variant] ?? $variants['primary']);
@endphp

@if ($href)
    <a href="{{ $href }}" wire:navigate {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $slot }}
    </a>
@else
    <button type="{{ $type }}" {{ $attributes->merge(['class' => $classes]) }}>
        @if ($icon) <x-icon :name="$icon" class="size-4" /> @endif
        {{ $slot }}
    </button>
@endif
