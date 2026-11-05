{{--
    Workflow buttons for a document using HandlesDocumentActions:
    <x-document-actions :document="$adjustment" :edit-route="..." :print-route="..." />
    Buttons appear only when the status allows them and the user has the permission.
    Actions that need a reason open a small form first.
--}}
@props(['document', 'editRoute' => null, 'printRoute' => null])

@php
    $key = $document->permissionKey();
    $user = auth()->user();
    $locked = app(\App\Support\Documents\PeriodLock::class)->isLocked($document->documentDate());
    $needsApproval = app(\App\Support\Documents\Approvals::class)->required($document->documentType());
    $canPost = ! $document->isPosted() && ($document->isApproved() || (! $needsApproval && $document->isDraft()));
@endphp

<div x-data="{ ask: null }" class="flex flex-wrap items-center gap-2">
    <x-status :status="$document->status" />

    @if ($document->isDraft() && $editRoute && $user->can("$key.edit"))
        <x-button variant="secondary" :href="$editRoute" icon="pencil">Edit</x-button>
    @endif
    @if ($document->isDraft() && $needsApproval && $user->can("$key.approve"))
        <x-button variant="secondary" wire:click="approveDocument" wire:confirm="Approve {{ $document->number }}?">Approve</x-button>
    @endif
    @if ($document->isApproved() && ! $document->isPosted() && $user->can("$key.approve"))
        <x-button variant="ghost" x-on:click="ask = 'draft'">Return to draft</x-button>
    @endif
    @if ($canPost && $user->can("$key.post"))
        @if ($locked)
            <x-button x-on:click="ask = 'post'">Post (locked period)</x-button>
        @else
            <x-button wire:click="postDocumentAction" wire:confirm="Post {{ $document->number }}? Posting changes stock and accounts and cannot be undone except by a reversal.">Post</x-button>
        @endif
    @endif
    @if (! $document->isPosted() && ! $document->isCancelled() && $user->can("$key.cancel"))
        <x-button variant="ghost" x-on:click="ask = 'cancel'">Cancel document</x-button>
    @endif
    @if ($printRoute && $user->can("$key.print"))
        <a href="{{ $printRoute }}" target="_blank" class="inline-flex items-center gap-1.5 rounded-md px-3.5 py-2 text-sm font-medium text-zinc-600 hover:bg-zinc-100 dark:text-zinc-400 dark:hover:bg-zinc-800"><x-icon name="printer" class="size-4" /> Print</a>
    @endif

    <div x-show="ask" x-cloak class="mt-2 flex w-full flex-wrap items-end gap-2 rounded-md bg-zinc-50 p-3 dark:bg-zinc-800/60">
        <x-input label="Reason" wire:model="actionReason" class="min-w-64 flex-1" />
        <x-button x-show="ask === 'cancel'" variant="danger" wire:click="cancelDocument" x-on:click="ask = null">Cancel document</x-button>
        <x-button x-show="ask === 'draft'" wire:click="returnDocumentToDraft" x-on:click="ask = null">Return to draft</x-button>
        <x-button x-show="ask === 'post'" wire:click="postDocumentAction" x-on:click="ask = null">Override lock and post</x-button>
        <x-button variant="ghost" x-on:click="ask = null">Close</x-button>
    </div>
</div>
