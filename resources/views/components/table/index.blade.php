{{--
    <x-table>
        <x-slot:head>
            <x-table.th sortable="name" :sort-field="$sortField" :sort-direction="$sortDirection">Name</x-table.th>
        </x-slot:head>
        @foreach ($rows as $row) <tr wire:key="row-{{ $row->id }}"> <x-table.td>...</x-table.td> </tr> @endforeach
    </x-table>
--}}
@props(['head' => null, 'empty' => 'Nothing found.'])

<div {{ $attributes->merge(['class' => 'relative overflow-x-auto']) }}>
    <table class="min-w-full divide-y divide-zinc-200 text-sm dark:divide-zinc-800">
        @if ($head)
            <thead class="bg-zinc-50 dark:bg-zinc-900/60">
                <tr>{{ $head }}</tr>
            </thead>
        @endif
        <tbody class="divide-y divide-zinc-100 bg-white dark:divide-zinc-800 dark:bg-zinc-900">
            @if (trim($slot) === '')
                <tr>
                    <td colspan="100" class="px-4 py-10 text-center text-sm text-zinc-500 dark:text-zinc-400">{{ $empty }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
