<div>
    <ol class="space-y-3">
        @forelse ($activities as $activity)
            <li class="text-sm" wire:key="act-{{ $activity->id }}">
                <p class="text-fg">
                    <span class="font-medium">{{ $activity->causer?->name ?? 'System' }}</span>
                    {{ mb_strtolower($activity->description) }}
                    <span class="text-xs text-subtle">· @datetime($activity->created_at)</span>
                </p>
                @php($props = $activity->properties)
                @if ($props->has('reason') || $props->has('override_reason'))
                    <p class="text-xs text-subtle">Reason: {{ $props->get('reason') ?? $props->get('override_reason') }}</p>
                @endif
                @if ($props->has('attributes'))
                    <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 text-xs text-subtle">
                        @foreach (collect($props->get('attributes'))->except([...$hidden, 'party_id', 'id'])->reject(fn ($v) => $activity->event === 'created' && ($v === null || $v === '')) as $field => $new)
                            <dt class="font-medium">{{ str_replace('_', ' ', $field) }}</dt>
                            <dd>
                                @php($old = $props->get('old')[$field] ?? null)
                                @if ($activity->event === 'updated' && $old !== null && $old !== '')
                                    <span class="line-through">{{ \App\Modules\Core\Livewire\Shared\ActivityHistory::display($old) }}</span> →
                                @endif
                                {{ \App\Modules\Core\Livewire\Shared\ActivityHistory::display($new) }}
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </li>
        @empty
            <li class="text-sm text-subtle">No activity yet.</li>
        @endforelse
    </ol>
</div>
