<div>
    <x-page-header title="Audit log" description="Every important change: who made it, when, the old and new values, and the reason where one was required." />

    <x-card :padding="false">
        <x-table.toolbar placeholder="Search description or record…" dates :export="auth()->user()->can('audit_log.export')">
            <select wire:model.live="user" aria-label="User" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All users</option>
                @foreach ($users as $id => $name)
                    <option value="{{ $id }}">{{ $name }}</option>
                @endforeach
            </select>
            <select wire:model.live="event" aria-label="Action" class="rounded-md border-0 bg-surface py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-line-strong">
                <option value="">All actions</option>
                @foreach ($events as $e)
                    <option value="{{ $e }}">{{ \Illuminate\Support\Str::headline($e) }}</option>
                @endforeach
            </select>
        </x-table.toolbar>

        <x-table empty="Nothing recorded for these filters.">
            <x-slot:head>
                <x-table.th sortable="created_at" :sort-field="$sortField" :sort-direction="$sortDirection">When</x-table.th>
                <x-table.th>User</x-table.th>
                <x-table.th>Action</x-table.th>
                <x-table.th>Record</x-table.th>
                <x-table.th>Details</x-table.th>
            </x-slot:head>
            @foreach ($activities as $a)
                <tr wire:key="a-{{ $a->id }}" class="align-top">
                    <x-table.td class="whitespace-nowrap">@datetime($a->created_at)</x-table.td>
                    <x-table.td>{{ $a->causer?->name ?? 'System' }}</x-table.td>
                    <x-table.td>{{ $a->description }}</x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        {{ $a->subject_type ? \Illuminate\Support\Str::headline(class_basename($a->subject_type)) : '—' }}
                        {{ $a->subject?->number ?? $a->subject?->code ?? $a->subject?->name ?? ($a->subject_id ? '#'.$a->subject_id : '') }}
                    </x-table.td>
                    <x-table.td class="max-w-md text-xs text-subtle">
                        @php($p = $a->properties)
                        @if ($p->has('reason') || $p->has('override_reason'))
                            <div><span class="font-medium">Reason:</span> {{ $p->get('reason') ?? $p->get('override_reason') }}</div>
                        @endif
                        @if ($p->has('attributes'))
                            @foreach ($p->get('attributes') as $field => $new)
                                <div class="truncate">
                                    <span class="font-medium">{{ str_replace('_', ' ', $field) }}:</span>
                                    @if (array_key_exists($field, $p->get('old', [])))
                                        <span class="line-through">{{ is_scalar($p->get('old')[$field]) ? $p->get('old')[$field] : json_encode($p->get('old')[$field]) }}</span> →
                                    @endif
                                    {{ is_scalar($new) ? $new : json_encode($new) }}
                                </div>
                            @endforeach
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>

        @if ($activities->hasPages())
            <div class="border-t border-line px-4 py-3">{{ $activities->links() }}</div>
        @endif
    </x-card>
</div>
