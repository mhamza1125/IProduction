<div>
    <form wire:submit="add" class="flex gap-2">
        <input type="text" wire:model="body" placeholder="Add a note…" aria-label="Note"
               class="block w-full rounded-md border-0 bg-white px-3 py-2 text-sm ring-1 ring-inset ring-zinc-300 focus:ring-2 focus:ring-brand-600 dark:bg-zinc-900 dark:ring-zinc-700">
        <x-button type="submit" size="sm" variant="secondary">Add</x-button>
    </form>
    @error('body') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror

    <ul class="mt-3 space-y-3">
        @foreach ($notes as $note)
            <li class="text-sm" wire:key="note-{{ $note->id }}">
                <p class="whitespace-pre-line text-zinc-800 dark:text-zinc-200">{{ $note->body }}</p>
                <p class="text-xs text-zinc-500">{{ $note->user?->name ?? 'System' }} · @datetime($note->created_at)</p>
            </li>
        @endforeach
    </ul>
</div>
