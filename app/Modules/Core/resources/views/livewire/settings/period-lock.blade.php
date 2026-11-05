<div>
    <x-page-header title="Settings" description="Nothing dated on or before the lock date can be posted or changed. Only users with the override permission can, and they must give a reason." />
    @include('core::settings-tabs')

    <form wire:submit="save" class="max-w-xl">
        <x-card>
            <div class="space-y-4">
                <x-date-input label="Lock date" wire:model="date" help="Leave empty to remove the lock." class="sm:w-48" />
                <x-textarea label="Reason" wire:model="reason" rows="2" required help="Recorded in the audit log." />
            </div>
            @can('period_lock.edit')
                <x-slot:footer><x-button type="submit">Save</x-button></x-slot:footer>
            @endcan
        </x-card>
    </form>
</div>
