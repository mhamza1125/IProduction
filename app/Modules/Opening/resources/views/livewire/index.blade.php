<div>
    <x-page-header title="Opening balances" description="Load starting balances from the old system as dated opening documents. Each file is checked row by row; you see a preview with errors and post only when every row is valid. The same file cannot be imported twice." />

    @if ($locked)
        <x-card class="mb-6"><p class="text-sm"><x-badge color="green">Go-live locked</x-badge> on {{ \App\Support\Format::datetime($lockedAt) }}. Opening documents are closed; correct balances with normal adjustments, journal vouchers or returns.</p></x-card>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-card title="Opening documents" :padding="false">
                <x-table empty="Nothing imported yet.">
                    <x-slot:head><x-table.th>Number</x-table.th><x-table.th>Type</x-table.th><x-table.th>Date</x-table.th><x-table.th>File</x-table.th><x-table.th align="right">Rows</x-table.th><x-table.th align="right">Errors</x-table.th><x-table.th>Status</x-table.th></x-slot:head>
                    @foreach ($batches as $b)
                        <tr wire:key="ob-{{ $b->id }}">
                            <x-table.td class="font-mono"><a href="{{ route('opening.show', $b) }}" wire:navigate class="text-brand-600 hover:underline">{{ $b->number }}</a></x-table.td>
                            <x-table.td>{{ $types[$b->type] ?? $b->type }}</x-table.td>
                            <x-table.td>@date($b->batch_date)</x-table.td>
                            <x-table.td class="text-xs">{{ $b->file_name }}</x-table.td>
                            <x-table.td align="right">{{ $b->row_count }}</x-table.td>
                            <x-table.td align="right" @class(['text-red-600 font-semibold' => $b->error_count > 0])>{{ $b->error_count }}</x-table.td>
                            <x-table.td><x-status :status="$b->status" /></x-table.td>
                        </tr>
                    @endforeach
                </x-table>
            </x-card>
            @can('reports.view') <p class="text-sm"><a href="{{ route('reports.show', 'cutover') }}" wire:navigate class="text-brand-600 hover:underline">Cutover report</a>: imported totals against the old system's control totals.</p> @endcan
        </div>
        <div class="space-y-6">
            @if (! $locked)
                @can('opening.create')
                    <x-card title="Import a file">
                        <form wire:submit="upload" class="grid gap-3">
                            <x-select label="Opening document" wire:model.live="type" :options="$types" />
                            <x-date-input label="Opening date" wire:model="batch_date" required />
                            <div>
                                <label class="text-sm font-medium text-zinc-700 dark:text-zinc-300">Excel or CSV file</label>
                                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="mt-1 block w-full text-sm">
                                @error('file') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                            </div>
                            <x-input label="Notes" wire:model="notes" />
                            <div class="flex items-center justify-between gap-2">
                                <a href="{{ route('opening.template', $type) }}" class="text-sm text-brand-600 hover:underline">Download template</a>
                                <x-button type="submit">Import and check</x-button>
                            </div>
                        </form>
                    </x-card>
                @endcan
                @can('opening.override')
                    <x-card title="Go-live" description="When every opening document is posted and the cutover report matches, lock go-live. After that no more opening documents can be added, and the period up to the opening date is locked.">
                        <form wire:submit="lock" class="grid gap-3">
                            <x-input label="Reason" wire:model="lock_reason" />
                            <x-button type="submit" variant="danger" wire:confirm="Lock go-live? This cannot be undone here.">Lock go-live</x-button>
                        </form>
                    </x-card>
                @endcan
            @endif
            <x-card title="Templates">
                <ul class="space-y-1 text-sm">@foreach ($types as $key => $label)<li><a href="{{ route('opening.template', $key) }}" class="text-brand-600 hover:underline">{{ $label }}</a></li>@endforeach</ul>
            </x-card>
        </div>
    </div>
</div>
