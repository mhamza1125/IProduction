<div>
    <x-page-header title="Dashboard" :description="'Welcome back, '.auth()->user()->name.'.'" />
    @if ($cards === [])
        <x-card><p class="text-sm text-zinc-600 dark:text-zinc-400">Nothing to show yet for your role.</p></x-card>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            @foreach ($cards as $c)
                <x-card>
                    <div class="flex items-start justify-between gap-2">
                        <p class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ $c['title'] }}</p>
                        @if ($c['url']) <a href="{{ $c['url'] }}" wire:navigate class="text-xs text-brand-600 hover:underline">Open</a> @endif
                    </div>
                    <p @class(['mt-1 text-2xl font-semibold tabular', 'text-red-600' => $c['alert'] ?? false])>{{ $c['value'] }}</p>
                    @if (! empty($c['detail']))<p class="mt-1 text-xs text-zinc-500">{{ $c['detail'] }}</p>@endif
                    @if (! empty($c['rows']))
                        <dl class="mt-3 space-y-1 text-sm">
                            @foreach ($c['rows'] as $i => $r)
                                <div class="flex justify-between gap-2"><dt class="truncate">@if (isset($c['links'][$i]))<a href="{{ $c['links'][$i] }}" wire:navigate class="text-brand-600 hover:underline">{{ $r[0] }}</a>@else{{ $r[0] }}@endif</dt><dd class="tabular">{{ $r[1] }}</dd></div>
                            @endforeach
                        </dl>
                    @endif
                </x-card>
            @endforeach
        </div>
    @endif
</div>
