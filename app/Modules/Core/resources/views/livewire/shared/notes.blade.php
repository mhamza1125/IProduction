<div>
    <form wire:submit="add" class="flex gap-2">
        <input type="text" wire:model="body" placeholder="Add a note…" aria-label="Note"
               class="block w-full rounded-md border-0 bg-surface px-3 py-2 text-sm ring-1 ring-inset ring-line-strong focus:ring-2 focus:ring-brand-600">
        <x-button type="submit" size="sm" variant="secondary">Add</x-button>
    </form>
    @error('body') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror

    <ul class="mt-3 space-y-3">
        @foreach ($notes as $note)
            <li class="text-sm" wire:key="note-{{ $note->id }}">
                <p class="whitespace-pre-line text-fg">{{ $note->body }}</p>
                <p class="text-xs text-subtle">{{ $note->user?->name ?? 'System' }} · @datetime($note->created_at)</p>
            </li>
        @endforeach
    </ul>
</div>
