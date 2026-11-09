<div>
    <x-page-header :title="$conversion ? 'Edit '.$conversion->number : 'New currency conversion'" description="Foreign money leaves the foreign account and the PKR you actually received enters the PKR account. No exchange rate is needed; the effective rate is shown for information." />
    <form wire:submit="save" data-save-shortcut>
        <x-card>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <x-date-input label="Date" wire:model="conversion_date" required />
                <x-select label="From (foreign account)" wire:model.live="from_account_id" :options="$foreign" placeholder="Choose…" required :help="$balance !== null ? 'Balance '.\App\Support\Format::money($balance, $from?->currency_code) : null" />
                <x-input :label="'Amount converted'.($from ? ' ('.$from->currency_code.')' : '')" type="number" step="any" min="0" wire:model.blur="from_amount" required />
                <x-select label="To (PKR account)" wire:model="to_account_id" :options="$pkr" placeholder="Choose…" required />
                <x-input label="PKR received" type="number" step="any" min="0" wire:model.blur="pkr_received" required />
                <x-input label="Deductions (PKR)" type="number" step="any" min="0" wire:model.blur="deductions" />
                <x-select label="Deduction head" wire:model="deduction_account_id" :options="$heads" placeholder="Bank charges" />
                <x-input label="Reference" wire:model="reference" />
                <div class="text-sm"><span class="block font-medium text-muted">Effective rate (information)</span><span class="mt-2 block text-lg font-semibold tabular">{{ $rate ?? '—' }}</span></div>
                <x-textarea label="Notes" wire:model="notes" rows="2" class="lg:col-span-3" />
            </div>
            <div class="mt-6 flex justify-end gap-2">
                <x-button variant="ghost" :href="$conversion ? route('finance.conversions.show', $conversion) : route('finance.conversions.index')">Cancel</x-button>
                <x-button type="submit">Save draft</x-button>
            </div>
        </x-card>
    </form>
</div>
