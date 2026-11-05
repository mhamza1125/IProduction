{{-- Label + control + help + validation error. Used by input/select/textarea. --}}
@props(['label' => null, 'for' => null, 'error' => null, 'help' => null, 'required' => false])

<div {{ $attributes->merge(['class' => 'space-y-1']) }}>
    @if ($label)
        <label for="{{ $for }}" class="block text-sm font-medium text-zinc-700 dark:text-zinc-300">
            {{ $label }}@if ($required)<span class="text-red-600"> *</span>@endif
        </label>
    @endif

    {{ $slot }}

    @if ($error)
        @error($error)
            <p class="text-xs text-red-600 dark:text-red-400">{{ $message }}</p>
        @enderror
    @endif

    @if ($help)
        <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ $help }}</p>
    @endif
</div>
