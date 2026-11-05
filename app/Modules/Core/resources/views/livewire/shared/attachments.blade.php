<div>
    <ul class="divide-y divide-zinc-100 dark:divide-zinc-800">
        @forelse ($attachments as $file)
            <li class="flex items-center gap-3 py-2 text-sm" wire:key="att-{{ $file->id }}">
                <a href="{{ route('core.attachments.download', $file) }}" class="min-w-0 flex-1 truncate font-medium text-brand-600 hover:underline dark:text-brand-500">{{ $file->original_name }}</a>
                <span class="shrink-0 text-xs text-zinc-500">{{ $file->humanSize() }} · {{ $file->uploader?->name }} · @date($file->created_at)</span>
                @if ($canEdit)
                    <button type="button" wire:click="delete({{ $file->id }})" wire:confirm="Remove {{ $file->original_name }}?" class="text-zinc-400 hover:text-red-600">
                        <x-icon name="trash" class="size-4" /><span class="sr-only">Remove</span>
                    </button>
                @endif
            </li>
        @empty
            <li class="py-2 text-sm text-zinc-500">No attachments.</li>
        @endforelse
    </ul>

    @if ($canEdit)
        <form wire:submit="upload" class="mt-3 flex flex-wrap items-center gap-2">
            <input type="file" wire:model="files" multiple class="text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-200">
            <x-button type="submit" size="sm" variant="secondary" wire:loading.attr="disabled">Upload</x-button>
            <span wire:loading wire:target="files" class="text-xs text-zinc-500">Uploading…</span>
        </form>
        @error('files') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        @error('files.*') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
        <p class="mt-1 text-xs text-zinc-500">PDF, images, Excel, Word or text. Up to {{ \App\Modules\Core\Models\Attachment::MAX_KB / 1024 }} MB each.</p>
    @endif
</div>
