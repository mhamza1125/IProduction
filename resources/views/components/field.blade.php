{{-- Label + control + help + validation error. Used by input/select/textarea. --}}
@props(['label' => null, 'for' => null, 'error' => null, 'help' => null, 'required' => false])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label for="{{ $for }}" class="block text-sm font-medium text-muted">
            {{ $label }}@if ($required)<span class="text-danger"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="text-xs text-danger">{{ $message }}</p>
        @enderror
    @endif

    @if ($help)
        <p class="text-xs text-subtle">{{ $help }}</p>
    @endif
</div>
