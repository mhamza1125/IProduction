@props(['href', 'active' => false, 'icon' => 'squares'])

<a href="{{ $href }}" wire:navigate @if ($active) aria-current="page" @endif
   {{ $attributes->class([
       'group flex items-center gap-2.5 rounded-md px-2.5 py-1.5 text-sm font-medium transition',
       'bg-zinc-100 text-zinc-900 dark:bg-zinc-800 dark:text-white' => $active,
       'text-zinc-600 hover:bg-zinc-50 hover:text-zinc-900 dark:text-zinc-400 dark:hover:bg-zinc-800/60 dark:hover:text-zinc-100' => ! $active,
   ]) }}>
    <x-icon :name="$icon" @class(['size-5', 'text-brand-600 dark:text-brand-500' => $active, 'text-zinc-400 group-hover:text-zinc-600 dark:group-hover:text-zinc-300' => ! $active]) />
    <span class="truncate">{{ $slot }}</span>
</a>
