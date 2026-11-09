<div>
    <ul class="divide-y divide-line">
        @forelse ($attachments as $file)
            <li class="flex items-center gap-3 py-2 text-sm" wire:key="att-{{ $file->id }}">
                <a href="{{ route('core.attachments.download', $file) }}" class="min-w-0 flex-1 truncate font-medium text-link hover:underline">{{ $file->original_name }}</a>
                <span class="shrink-0 text-xs text-subtle">{{ $file->humanSize() }} · {{ $file->uploader?->name }} · @date($file->created_at)</span>
                @if ($canEdit)
                    <button type="button" wire:click="delete({{ $file->id }})" wire:confirm="Remove {{ $file->original_name }}?" class="text-subtle hover:text-danger">
                        <x-icon name="trash" class="size-4" /><span class="sr-only">Remove</span>
                    </button>
                @endif
            </li>
        @empty
            <li class="py-2 text-sm text-subtle">No attachments.</li>
        @endforelse
    </ul>

    @if ($canEdit)
        <form wire:submit="upload" class="mt-3 flex flex-wrap items-center gap-2">
            <input type="file" wire:model="files" multiple class="text-sm text-muted file:mr-3 file:rounded-md file:border-0 file:bg-hover file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-fg">
            <x-button type="submit" size="sm" variant="secondary" wire:loading.attr="disabled">Upload</x-button>
            <span wire:loading wire:target="files" class="text-xs text-subtle">Uploading…</span>
        </form>
        @error('files') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        @error('files.*') <p class="mt-1 text-xs text-danger">{{ $message }}</p> @enderror
        <p class="mt-1 text-xs text-subtle">PDF, images, Excel, Word or text. Up to {{ \App\Modules\Core\Models\Attachment::MAX_KB / 1024 }} MB each.</p>
    @endif
</div>
