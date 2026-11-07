<div>
    <x-page-header title="Wage work" description="Pieces accepted from wage employees at the route rate. Review before payroll; approval posts the wages payable. Rejected pieces are unpaid unless approved." />
    @can('wage_work.create')
        <x-card class="mb-6" title="Add work done (not from a PTC)">
            <form wire:submit="addManual" class="grid gap-3 sm:grid-cols-2 lg:grid-cols-6">
                <x-select label="Employee" wire:model="new_employee_id" :options="$wageEmployees" placeholder="Choose…" class="lg:col-span-2" />
                <x-date-input label="Date" wire:model="new_date" />
                <x-input label="Work" wire:model="new_description" placeholder="Other work description" />
                <x-input label="Quantity" type="number" step="any" min="0" wire:model="new_quantity" />
                <x-input label="Rate" type="number" step="any" min="0" wire:model="new_rate" />
                <div class="flex items-end lg:col-span-6"><x-button type="submit" size="sm" variant="secondary">Add</x-button></div>
            </form>
        </x-card>
    @endcan
    <x-card :padding="false">
        <x-table.toolbar placeholder="Search employee, PTC…" dates :export="auth()->user()->can('wage_work.export')">
            <select wire:model.live="status" aria-label="Status" class="rounded-md border-0 bg-white py-2 pl-3 pr-8 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">
                <option value="">All</option><option value="pending">Pending</option><option value="approved">Approved</option><option value="rejected">Rejected</option>
            </select>
            @can('wage_work.approve') <input wire:model="reason" placeholder="Reason (to reject)" aria-label="Reason" class="rounded-md border-0 py-2 text-sm ring-1 ring-inset ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700"> @endcan
        </x-table.toolbar>
        <x-table empty="No wage work.">
            <x-slot:head>
                <x-table.th sortable="work_date" :sort-field="$sortField" :sort-direction="$sortDirection">Date</x-table.th>
                <x-table.th>Employee</x-table.th><x-table.th>PTC · stage</x-table.th>
                <x-table.th sortable="quantity" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Accepted</x-table.th><x-table.th align="right">Rejected</x-table.th>
                @can('salary.view')<x-table.th align="right">Rate</x-table.th><x-table.th sortable="amount" :sort-field="$sortField" :sort-direction="$sortDirection" align="right">Amount</x-table.th>@endcan
                <x-table.th>Status</x-table.th><x-table.th></x-table.th>
            </x-slot:head>
            @foreach ($rows as $w)
                <tr wire:key="ww-{{ $w->id }}">
                    <x-table.td>@date($w->work_date)</x-table.td>
                    <x-table.td>{{ $w->employee->name }}</x-table.td>
                    <x-table.td class="text-xs">{{ $w->description }} @if ($w->ptc)<a href="{{ route('production.ptcs.show', $w->ptc) }}" wire:navigate class="font-mono text-brand-600 hover:underline">{{ $w->ptc->number }}</a>@endif {{ $w->stage?->name }}</x-table.td>
                    <x-table.td align="right">@qty($w->quantity)</x-table.td>
                    <x-table.td align="right">@qty($w->rejected_qty)@if ((float) $w->paid_rejected_qty)<div class="text-xs">paid @qty($w->paid_rejected_qty)</div>@endif</x-table.td>
                    @can('salary.view')<x-table.td align="right">@money($w->rate, null, 4)</x-table.td><x-table.td align="right">@money($w->amount)</x-table.td>@endcan
                    <x-table.td><x-status :status="$w->status" /></x-table.td>
                    <x-table.td class="whitespace-nowrap">
                        @if ($w->status === 'pending')
                            @can('wage_work.approve')
                                @can('wage_work.override') @if ((float) $w->rejected_qty)<input type="number" step="any" min="0" wire:model="paidRejected.{{ $w->id }}" placeholder="Pay rejected" aria-label="Rejected pieces to pay" class="w-24 rounded-md border-0 py-1 text-right text-xs ring-1 ring-zinc-300 dark:bg-zinc-900 dark:ring-zinc-700">@endif @endcan
                                <x-button size="sm" wire:click="approve({{ $w->id }})">Approve</x-button>
                                <x-button size="sm" variant="ghost" wire:click="reject({{ $w->id }})">Reject</x-button>
                            @endcan
                        @endif
                    </x-table.td>
                </tr>
            @endforeach
        </x-table>
        @if ($rows->hasPages()) <div class="border-t border-zinc-200 px-4 py-3 dark:border-zinc-800">{{ $rows->links() }}</div> @endif
    </x-card>
</div>
