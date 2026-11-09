<div>
    <x-page-header title="Reports" description="Reports use posted data. Cost, salary and profit figures need their own permission." />
    <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($groups as $group => $reports)
            <x-card :title="$group">
                <ul class="space-y-2 text-sm">
                    @foreach ($reports as $r)
                        <li><a href="{{ route('reports.show', $r->key()) }}" wire:navigate class="font-medium text-link hover:underline">{{ $r->title() }}</a>@if ($r->description())<div class="text-xs text-subtle">{{ $r->description() }}</div>@endif</li>
                    @endforeach
                </ul>
            </x-card>
        @empty
            <x-card><p class="text-sm text-subtle">You do not have access to any reports.</p></x-card>
        @endforelse
    </div>
</div>
