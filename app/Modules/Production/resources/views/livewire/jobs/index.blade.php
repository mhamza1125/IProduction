<div>
    <x-page-header title="Contractor jobs" description="Work given to contractors. Material at a contractor is still company stock.">
        @can('contractor_jobs.create') <x-button :href="route('production.jobs.create')" icon="plus">New job</x-button> @endcan
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search number, contractor…" dates :export="auth()->user()->can('contractor_jobs.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All statuses</option>@foreach ($statuses as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="No contractor jobs.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th sortable="job_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Contractor</x-table.th><x-table.th>Output</x-table.th>
                <x-table.th sortable="due_date" :sort-field="$sortField" :sort-direction="$sortDirection">Due</x-table.th><x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($jobs as $j)
                <tr wire:key="cj-{{ $j->id }}">
                    <x-table.td class="font-mono"><a href="{{ route('production.jobs.show', $j) }}" wire:navigate class="text-brand-600 hover:underline">{{ $j->number }}</a></x-table.td>
                    <x-table.td>@date($j->job_date)</x-table.td>
                    <x-table.td>{{ $j->contractor->name }}</x-table.td>
                    <x-table.td class="text-xs">@foreach ($j->lines as $l)<div>{{ $l->variant->sku }}: @qty($l->received_qty) / @qty($l->quantity)</div>@endforeach</x-table.td>
                    <x-table.td @class(['text-red-600 font-medium' => $j->isOpen() && $j->due_date?->isPast()])>@date($j->due_date)</x-table.td>
                    <x-table.td><x-status :status="$j->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($jobs->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $jobs->links() }}</div> @endif
    </x-card>
</div>
