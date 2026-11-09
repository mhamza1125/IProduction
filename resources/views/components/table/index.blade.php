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
    <table class="min-w-full divide-y divide-line text-sm">
        @if ($head)
            <thead class="bg-sunken">
                <tr>{{ $head }}</tr>
            </thead>
        @endif
        <tbody class="divide-y divide-line bg-surface">
            @if (trim($slot) === '')
                <tr>
                    <td colspan="100" class="px-4 py-10 text-center text-sm text-subtle">{{ $empty }}</td>
                </tr>
            @else
                {{ $slot }}
            @endif
        </tbody>
    </table>
</div>
