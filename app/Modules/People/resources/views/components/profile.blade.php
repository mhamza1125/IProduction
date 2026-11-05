{{--
    Profile page frame shared by customers, vendors, employees and contractors.
    <x-people::profile :record="$customer" resource="customers" :edit-route="route(...)" :index-route="route(...)">
        <x-slot:details> <dl>...</dl> </x-slot:details>
    </x-people::profile>
--}}
@props(['record', 'resource', 'editRoute', 'indexRoute', 'subtitle' => null, 'details', 'side' => null])

<div>
    <x-page-header :title="$record->name" :description="$record->code.($subtitle ? ' · '.$subtitle : '')">
        <x-status :status="$record->is_active ? 'active' : 'inactive'" />
        @can("$resource.edit")
            <x-button variant="secondary" wire:click="toggleActive" wire:confirm="{{ $record->is_active ? 'Deactivate' : 'Activate' }} {{ $record->name }}?">
                {{ $record->is_active ? 'Deactivate' : 'Activate' }}
            </x-button>
            <x-button :href="$editRoute" icon="pencil">Edit</x-button>
        @endcan
        @can("$resource.delete")
            <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete {{ $record->name }}? Records used in transactions can only be deactivated.">Delete</x-button>
        @endcan
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Details">
                {{ $details }}
            </x-card>

            {{ $slot }}

            <x-card title="Attachments">
                <livewire:core::shared.attachments :model="$record" :key="'att-'.$record->id" />
            </x-card>
        </div>

        <div class="space-y-6">
            {{ $side ?? '' }}

            <x-card title="Notes">
                <livewire:core::shared.notes :model="$record" :key="'notes-'.$record->id" />
            </x-card>

            <x-card title="History">
                <livewire:core::shared.activity-history :model="$record" :key="'hist-'.$record->id" />
            </x-card>
        </div>
    </div>
</div>
