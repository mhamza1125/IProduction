{{-- <x-status :status="$doc->status" /> : coloured badge for any document status. --}}
@props(['status'])

@php
    $status = (string) $status;
    $color = match (true) {
        in_array($status, ['draft'], true) => 'zinc',
        in_array($status, ['approved', 'sent', 'allocated', 'packed', 'in_planning'], true) => 'blue',
        in_array($status, ['posted', 'completed', 'received', 'delivered', 'dispatched', 'paid', 'closed', 'active'], true) => 'green',
        in_array($status, ['cancelled', 'rejected', 'inactive'], true) => 'red',
        default => 'amber', // in progress, partly ..., on hold, closed short ...
    };
@endphp

<x-badge :color="$color" {{ $attributes }}>{{ \Illuminate\Support\Str::headline($status) }}</x-badge>
