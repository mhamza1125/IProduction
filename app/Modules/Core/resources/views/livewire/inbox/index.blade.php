<div class="space-y-6">
    <x-page-header title="Inbox" description="Documents waiting for your approval and alerts from the system. You cannot approve what you created yourself, so those are not listed here." />

    <x-card :padding="false">
        <div class="border-b border-line px-4 py-3 text-sm font-semibold text-fg">Awaiting your approval</div>
        @if ($queues)
            <div class="flex flex-wrap gap-2 border-b border-line px-4 py-3">
                @foreach ($queues as $q)
                    <a href="{{ $q['url'] }}" wire:navigate class="rounded-md bg-sunken px-3 py-1.5 text-sm text-link hover:underline">{{ $q['label'] }}: {{ $q['count'] }} pending</a>
                @endforeach
            </div>
        @endif
        <x-table empty="Nothing is waiting for your approval.">
            <x-slot:head>
                <x-table.th>Document</x-table.th><x-table.th>Type</x-table.th><x-table.th>Date</x-table.th><x-table.th>Created by</x-table.th>
            </x-slot:head>
            @foreach ($documents as $d)
                <tr>
                    <x-table.td class="font-mono">
                        @if ($d['url']) <a href="{{ $d['url'] }}" wire:navigate class="text-link hover:underline">{{ $d['number'] }}</a> @else {{ $d['number'] }} @endif
                    </x-table.td>
                    <x-table.td>{{ $d['type'] }}</x-table.td>
                    <x-table.td>@date($d['date'])</x-table.td>
                    <x-table.td>{{ $d['by'] ?? '—' }}</x-table.td>
                </tr>
            @endforeach
        </x-table>
    </x-card>

    <x-card :padding="false">
        <div class="flex items-center justify-between border-b border-line px-4 py-3">
            <span class="text-sm font-semibold text-fg">Alerts</span>
            @if (auth()->user()->unreadNotifications()->exists())
                <x-button size="sm" variant="ghost" wire:click="markAllRead">Mark all as read</x-button>
            @endif
        </div>
        <x-table empty="No alerts.">
            <x-slot:head>
                <x-table.th>When</x-table.th><x-table.th>Alert</x-table.th><x-table.th></x-table.th>
            </x-slot:head>
            @foreach ($notifications as $n)
                <tr wire:key="n-{{ $n->id }}" @class(['font-medium' => $n->read_at === null])>
                    <x-table.td class="whitespace-nowrap">@date($n->created_at) {{ $n->created_at->format('H:i') }}</x-table.td>
                    <x-table.td wide>
                        <div @class(['text-danger' => ($n->data['level'] ?? '') === 'danger' && $n->read_at === null, 'text-warning' => ($n->data['level'] ?? '') === 'warning' && $n->read_at === null, 'text-fg' => $n->read_at === null])>{{ $n->data['title'] ?? 'Alert' }}</div>
                        <div class="text-xs text-muted">{{ $n->data['message'] ?? '' }}</div>
                        @if (! empty($n->data['url'])) <a href="{{ $n->data['url'] }}" class="text-xs text-link hover:underline">Open</a> @endif
                    </x-table.td>
                    <x-table.td align="right">
                        @if ($n->read_at === null) <x-button size="sm" variant="ghost" wire:click="markRead('{{ $n->id }}')">Mark read</x-button> @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($notifications->hasPages()) <div class="border-t border-line px-4 py-3">{{ $notifications->links() }}</div> @endif
    </x-card>
</div>
