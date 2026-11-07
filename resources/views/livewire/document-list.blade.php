<div>
    <x-page-header :title="$heading" :description="$blurb">
        @if ($create) @can($perm.'.create') <x-button :href="route($create)" icon="plus">New</x-button> @endcan @endif
    </x-page-header>
    <x-card :padding="false">
        <x-table.toolbar :placeholder="$searchPlaceholder" dates :export="auth()->user()->can($perm.'.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All statuses</option>@foreach ($statusOptions as $s)<option value="{{ $s }}">{{ \Illuminate\Support\Str::headline($s) }}</option>@endforeach
            </select>
        </x-table.toolbar>
        <x-table empty="Nothing here yet.">
            <x-slot:head>
                <x-table.th sortable="number" :sort-field="$sortField" :sort-direction="$sortDirection">Number</x-table.th>
                <x-table.th :sortable="$dateColumn" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                @foreach ($columns as $label => $fn)<x-table.th :align="in_array($label, $right, true) ? 'right' : 'left'">{{ $label }}</x-table.th>@endforeach
                <x-table.th>Status</x-table.th>
            </x-slot:head>
            @foreach ($rows as $row)
                <tr wire:key="r-{{ $row->id }}">
                    <x-table.td class="font-mono"><a href="{{ route($show, $row) }}" wire:navigate class="text-brand-600 hover:underline">{{ $row->number }}</a></x-table.td>
                    <x-table.td>@date($row->getAttribute($dateColumn))</x-table.td>
                    @foreach ($columns as $label => $fn)<x-table.td :align="in_array($label, $right, true) ? 'right' : 'left'">{{ $fn($row) }}</x-table.td>@endforeach
                    <x-table.td><x-status :status="$row->status" /></x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($rows->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $rows->links() }}</div> @endif
    </x-card>
</div>
