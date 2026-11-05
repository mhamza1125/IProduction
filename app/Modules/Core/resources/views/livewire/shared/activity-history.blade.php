<div>
    <ol class="space-y-3">
        @forelse ($activities as $activity)
            <li class="text-sm" wire:key="act-{{ $activity->id }}">
                <p class="text-zinc-800 dark:text-zinc-200">
                    <span class="font-medium">{{ $activity->causer?->name ?? 'System' }}</span>
                    {{ mb_strtolower($activity->description) }}
                    <span class="text-xs text-zinc-500">· @datetime($activity->created_at)</span>
                </p>
                @php($props = $activity->properties)
                @if ($props->has('reason') || $props->has('override_reason'))
                    <p class="text-xs text-zinc-500">Reason: {{ $props->get('reason') ?? $props->get('override_reason') }}</p>
                @endif
                @if ($props->has('attributes'))
                    <dl class="mt-1 grid grid-cols-[auto_1fr] gap-x-3 text-xs text-zinc-500">
                        @foreach (collect($props->get('attributes'))->except([...$hidden, 'party_id', 'id'])->reject(fn ($v) => $activity->event === 'created' && ($v === null || $v === '')) as $field => $new)
                            <dt class="font-medium">{{ str_replace('_', ' ', $field) }}</dt>
                            <dd>
                                @if (isset($props->get('old')[$field]))
                                    <span class="line-through">{{ is_scalar($props->get('old')[$field]) ? $props->get('old')[$field] : json_encode($props->get('old')[$field]) }}</span> →
                                @endif
                                {{ is_scalar($new) || $new === null ? ($new ?? '—') : json_encode($new) }}
                            </dd>
                        @endforeach
                    </dl>
                @endif
            </li>
        @empty
            <li class="text-sm text-zinc-500">No activity yet.</li>
        @endforelse
    </ol>
</div>
