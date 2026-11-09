@props(['href', 'active' => false, 'icon' => 'squares'])

<a href="{{ $href }}" wire:navigate @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'group relative flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500',
       'bg-selected font-semibold text-selected-fg before:absolute before:inset-y-1.5 before:left-0 before:w-[3px] before:rounded-full before:bg-primary' => $active,
       'font-medium text-muted hover:bg-hover hover:text-fg' => ! $active,
   ]) }}>
    <x-icon :name="$icon" @class(['size-5', 'text-selected-fg' => $active, 'text-subtle group-hover:text-fg' => ! $active]) />
    <span class="truncate">{{ $slot }}</span>
</a>
