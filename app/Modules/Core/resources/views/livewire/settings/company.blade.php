<div>
    <x-page-header title="Settings" description="Company details appear on screens, invoices and other printed documents." />
    @include('core::settings-tabs')

    <form wire:submit="save" data-save-shortcut class="max-w-3xl space-y-6">
        <x-card title="Company">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-input label="Company name" wire:model="name" required class="sm:col-span-2" />
                <x-textarea label="Address" wire:model="address" rows="3" class="sm:col-span-2" />
                <x-input label="Phone" wire:model="phone" />
                <x-input label="Email" type="email" wire:model="email" />
                <x-input label="NTN" wire:model="ntn" help="National Tax Number" />
                <x-input label="STRN" wire:model="strn" help="Sales Tax Registration Number" />
                <x-input label="CNIC" wire:model="cnic" help="Owner CNIC, if printed on documents" />
            </div>
        </x-card>

        <x-card title="Printed documents">
            <div class="grid gap-4">
                <x-input label="Print header text" wire:model="print_header" help="One line under the company details, e.g. a slogan or licence number." />
                <x-input label="Print footer text" wire:model="print_footer" />
                <x-textarea label="Invoice terms" wire:model="invoice_terms" rows="4" />
            </div>
        </x-card>

        <x-card title="Logo">
            <div class="flex items-center gap-6">
                <div class="flex h-20 w-40 items-center justify-center rounded-md bg-zinc-100 dark:bg-zinc-800">
                    @if ($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="New logo" class="max-h-16 max-w-36">
                    @elseif ($logoPath)
                        <img src="{{ Storage::disk('public')->url($logoPath) }}" alt="Current logo" class="max-h-16 max-w-36">
                    @else
                        <span class="text-xs text-zinc-400">No logo</span>
                    @endif
                </div>
                <div>
                    <input type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp" class="text-sm text-zinc-600 file:mr-3 file:rounded-md file:border-0 file:bg-zinc-100 file:px-3 file:py-1.5 file:text-sm file:font-medium file:text-zinc-700 hover:file:bg-zinc-200 dark:text-zinc-400 dark:file:bg-zinc-800 dark:file:text-zinc-200">
                    <p class="mt-1 text-xs text-zinc-500">PNG, JPG or WebP, up to 2 MB. Resized to 600px wide.</p>
                    <div wire:loading wire:target="logo" class="text-xs text-zinc-500">Uploading…</div>
                    @error('logo') <p class="mt-1 text-xs text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>
        </x-card>

        <x-card title="Accounting and tolerances">
            <div class="grid gap-4 sm:grid-cols-2">
            <x-select label="Fiscal year starts in" wire:model="fiscal_year_start_month" class="sm:w-64"
                      :options="collect(range(1, 12))->mapWithKeys(fn ($m) => [$m => \Carbon\Carbon::create(2000, $m, 1)->format('F')])->all()"
                      help="Pakistan: July. Used for fiscal-year reports." />
            <x-input label="Over-receipt tolerance (%)" type="number" step="0.01" min="0" max="100" wire:model="over_receipt_percent"
                     help="Extra quantity a vendor or contractor may deliver without an authorised decision. Default 0." />
            </div>
        </x-card>

        @can('settings.edit')
            <div class="flex justify-end">
                <x-button type="submit" wire:loading.attr="disabled">Save settings</x-button>
            </div>
        @endcan
    </form>
</div>
