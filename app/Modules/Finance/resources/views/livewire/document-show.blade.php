<div>
    <x-page-header :title="$heading" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-document-actions :document="$doc" :edit-route="route($edit, $doc)" :print-route="$print" />
        @if ($doc->isDraft()) @can($doc->permissionKey().'.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete draft?">Delete</x-button> @endcan @endif
    </div>
    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            @if ($body) @include($body) @endif
            <x-card title="Attachments"><livewire:core::shared.attachments :model="$doc" :key="'a-'.$doc->id" /></x-card>
        </div>
        <div class="space-y-6">
            <x-card title="Details">
                <dl class="grid gap-3">
                    @foreach ($details as $label => $value)<x-people::detail :label="$label" :value="$value" />@endforeach
                    @if ($doc->cancel_reason) <x-people::detail label="Cancel reason" :value="$doc->cancel_reason" /> @endif
                </dl>
            </x-card>
            @if ($journals->isNotEmpty())
                <x-card title="Journals">
                    <ul class="space-y-1 text-sm">@foreach ($journals as $j)<li>@can('journals.view')<a href="{{ route('finance.journals.show', $j) }}" wire:navigate class="font-mono text-link hover:underline">{{ $j->number }}</a>@else <span class="font-mono">{{ $j->number }}</span>@endcan · {{ $j->currency_code }} · {{ $j->description }}</li>@endforeach</ul>
                </x-card>
            @endif
            <x-card title="Notes"><livewire:core::shared.notes :model="$doc" :key="'n-'.$doc->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$doc" :key="'h-'.$doc->id" /></x-card>
        </div>
    </div>
</div>
