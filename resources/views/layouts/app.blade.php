{{-- Main application shell: sidebar navigation built from the module registry. --}}
@php
    $settings = app(\App\Modules\Core\Services\Settings::class);
    $user = auth()->user();
    $navigation = app(\App\Support\Modules\ModuleRegistry::class)->navigationFor($user);
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="h-full bg-page text-fg">
<div x-data="{ sidebar: false }" class="min-h-full">
    {{-- Mobile sidebar backdrop --}}
    <div x-show="sidebar" x-cloak x-transition.opacity class="fixed inset-0 z-40 bg-black/50 lg:hidden" x-on:click="sidebar = false"></div>

    {{-- Sidebar --}}
    <aside
        :class="sidebar ? 'translate-x-0' : '-translate-x-full'"
        class="fixed inset-y-0 left-0 z-50 flex w-64 -translate-x-full flex-col border-r border-line bg-chrome transition-transform lg:translate-x-0"
    >
        <div class="flex h-14 items-center gap-2 border-b border-line px-4">
            @if ($logo = $settings->get('company.logo_path'))
                <img src="{{ Storage::disk('public')->url($logo) }}" alt="" class="h-7 w-auto">
            @endif
            <span class="truncate text-sm font-semibold">{{ $settings->get('company.name') }}</span>
            <button type="button" class="ml-auto rounded p-1 text-subtle lg:hidden" x-on:click="sidebar = false">
                <x-icon name="x" /><span class="sr-only">Close menu</span>
            </button>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4" aria-label="Main">
            <div class="space-y-0.5 pb-3">
                <x-nav.link :href="route('dashboard')" :active="request()->routeIs('dashboard')" icon="home">Dashboard</x-nav.link>
            </div>

            @foreach ($navigation as $group => $items)
                @php($groupActive = collect($items)->contains(fn ($i) => $i->isActive()))
                <div class="border-t border-line pt-2 pb-1" x-data="{ open: $persist({{ $groupActive ? 'true' : 'false' }}).as('nav-{{ \Illuminate\Support\Str::slug($group) }}') }"
                     @if ($groupActive) x-init="open = true" @endif>
                    <button type="button" x-on:click="open = ! open" :aria-expanded="open"
                            class="flex w-full items-center justify-between rounded px-2.5 pb-1.5 text-[11px] font-semibold uppercase tracking-wider text-subtle transition hover:text-fg focus:outline-none focus-visible:ring-2 focus-visible:ring-brand-500">
                        {{ $group }}
                        <x-icon name="chevron-down" class="size-3.5 transition" ::class="open ? '' : '-rotate-90'" />
                    </button>
                    <div x-show="open" x-collapse class="space-y-0.5">
                        @foreach ($items as $item)
                            <x-nav.link :href="route($item->route)" :active="$item->isActive()" :icon="$item->icon">{{ $item->label }}</x-nav.link>
                        @endforeach
                    </div>
                </div>
            @endforeach
        </nav>
    </aside>

    <div class="lg:pl-64">
        {{-- Top bar --}}
        <header class="sticky top-0 z-30 flex h-14 items-center gap-3 border-b border-line bg-chrome/90 px-4 backdrop-blur sm:px-6">
            <button type="button" class="-ml-1 rounded p-1 text-subtle lg:hidden" x-on:click="sidebar = true">
                <x-icon name="menu" /><span class="sr-only">Open menu</span>
            </button>

            <div class="ml-auto flex items-center gap-1">
                {{-- Theme: light, dim, dark or follow the system (resources/js/theme.js). --}}
                <div x-data="{ open: false, theme: window.currentTheme() }" x-on:theme-changed.window="theme = $event.detail || 'system'"
                     class="relative" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                    <button type="button" x-on:click="open = !open" :aria-expanded="open" aria-haspopup="menu" class="rounded-md p-2 text-subtle hover:bg-hover hover:text-fg" title="Theme">
                        <x-icon name="sun" x-show="theme === 'light'" />
                        <x-icon name="dim" x-show="theme === 'dim'" x-cloak />
                        <x-icon name="moon" x-show="theme === 'dark'" x-cloak />
                        <x-icon name="desktop" x-show="theme === 'system'" x-cloak />
                        <span class="sr-only">Theme</span>
                    </button>
                    <div x-show="open" x-cloak x-transition role="menu" class="absolute right-0 mt-1 w-40 rounded-md bg-raised py-1 text-fg shadow-lg ring-1 ring-line-strong/40">
                        @foreach (['light' => ['Light', 'sun'], 'dim' => ['Dim', 'dim'], 'dark' => ['Dark', 'moon'], 'system' => ['System', 'desktop']] as $value => [$label, $icon])
                            <button type="button" role="menuitemradio" :aria-checked="theme === '{{ $value }}'"
                                    x-on:click="setTheme('{{ $value }}'); open = false"
                                    class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-hover"
                                    :class="theme === '{{ $value }}' ? 'font-semibold text-selected-fg' : ''">
                                <x-icon :name="$icon" class="size-4" /> {{ $label }}
                                <x-icon name="check" class="ml-auto size-4" x-show="theme === '{{ $value }}'" />
                            </button>
                        @endforeach
                    </div>
                </div>

                <div x-data="{ open: false }" class="relative" x-on:click.outside="open = false" x-on:keydown.escape="open = false">
                    <button type="button" x-on:click="open = !open" class="flex items-center gap-2 rounded-md px-2 py-1.5 text-sm hover:bg-hover" :aria-expanded="open">
                        <span class="flex size-7 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white">{{ $user->initials() }}</span>
                        <span class="hidden max-w-40 truncate sm:block">{{ $user->name }}</span>
                    </button>
                    <div x-show="open" x-cloak x-transition class="absolute right-0 mt-1 w-48 rounded-md bg-raised py-1 text-fg shadow-lg ring-1 ring-line-strong/40">
                        <a href="{{ route('profile') }}" wire:navigate class="flex items-center gap-2 px-3 py-2 text-sm hover:bg-hover">
                            <x-icon name="user" class="size-4" /> Profile
                        </a>
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf
                            <button type="submit" class="flex w-full items-center gap-2 px-3 py-2 text-left text-sm hover:bg-hover hover:text-danger">
                                <x-icon name="logout" class="size-4" /> Sign out
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </header>

        <main class="px-4 py-6 sm:px-6 lg:px-8">
            {{ $slot }}
        </main>
    </div>
</div>

<x-toasts />
</body>
</html>
