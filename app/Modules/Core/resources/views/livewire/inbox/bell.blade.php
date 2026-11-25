<div wire:poll.60s>
    @php $total = $alerts + $approvals; @endphp
    <a href="{{ route('core.inbox') }}" wire:navigate class="relative flex rounded-md p-2 text-subtle hover:bg-hover hover:text-fg"
       title="{{ $alerts }} unread {{ str('alert')->plural($alerts) }}, {{ $approvals }} awaiting your approval">
        <x-icon name="bell" />
        @if ($total > 0)
            <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full px-1 text-[10px] font-semibold leading-4 text-white {{ $alerts > 0 ? 'bg-red-600' : 'bg-primary' }}">{{ $total > 99 ? '99+' : $total }}</span>
        @endif
        <span class="sr-only">Inbox: {{ $alerts }} unread alerts, {{ $approvals }} awaiting your approval</span>
    </a>
</div>
