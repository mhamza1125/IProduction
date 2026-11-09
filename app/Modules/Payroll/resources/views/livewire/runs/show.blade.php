<div>
    <x-page-header :title="'Payroll '.$run->monthLabel()" :description="$run->number.' · '.($run->department?->name ?? 'All departments').' · '.($run->employee_type ? \App\Modules\People\Models\Employee::TYPES[$run->employee_type] : 'all employee types')" />
    <div class="mb-6 flex flex-wrap items-center gap-2">
        <x-document-actions :document="$run" :print-route="$run->isDraft() ? null : route('payroll.runs.print', $run)" />
        @if ($run->isDraft())
            @can('payroll.edit') <x-button variant="secondary" wire:click="recalculate">Recalculate</x-button> @endcan
            @can('payroll.delete') <x-button variant="ghost" icon="trash" wire:click="delete" wire:confirm="Delete this draft payroll?">Delete</x-button> @endcan
        @endif
    </div>

    @if (! $canSee)
        <x-card><p class="text-sm text-subtle">Salary amounts are hidden. You need the salary permission to see them.</p></x-card>
    @else
        <x-card :padding="false" :description="$run->isDraft() ? 'Nothing changes until the payroll is posted. Recalculate after changing attendance, wage work, loans or advances.' : null">
            <x-table empty="No employees in this payroll.">
                <x-slot:head>
                    <x-table.th>Employee</x-table.th><x-table.th align="right">Salary / wage work</x-table.th><x-table.th align="right">Overtime + allowances</x-table.th>
                    <x-table.th align="right">Attendance</x-table.th><x-table.th align="right">Fines</x-table.th><x-table.th align="right">Loan</x-table.th><x-table.th align="right">Advance</x-table.th>
                    <x-table.th align="right">Brought fwd</x-table.th><x-table.th align="right">Net payable</x-table.th><x-table.th align="right">Paid</x-table.th><x-table.th></x-table.th>
                </x-slot:head>
                @foreach ($lines as $l)
                    <tr wire:key="pl-{{ $l->id }}">
                        <x-table.td>{{ $l->employee->name }}<div class="text-xs text-subtle">{{ $l->employee->code }} · {{ ucfirst($l->employee_type) }}</div></x-table.td>
                        <x-table.td align="right">@money($l->earnings)</x-table.td>
                        <x-table.td align="right">@money(\App\Support\Decimal::add($l->overtime, $l->allowances))</x-table.td>
                        <x-table.td align="right">@if ((float) $l->attendance_deduction)−@money($l->attendance_deduction)@endif @if ($l->deduction_override_reason)<div class="text-xs text-warning" title="{{ $l->deduction_override_reason }}">edited</div>@endif</x-table.td>
                        <x-table.td align="right">@if ((float) $l->fines)−@money($l->fines)@endif</x-table.td>
                        <x-table.td align="right">@if ((float) $l->loan_installment)−@money($l->loan_installment)@endif</x-table.td>
                        <x-table.td align="right">@if ((float) $l->advance_deduction)−@money($l->advance_deduction)@endif</x-table.td>
                        <x-table.td align="right">@if ((float) $l->brought_forward)−@money($l->brought_forward)@endif</x-table.td>
                        <x-table.td align="right" class="font-semibold">@money($l->net_payable)@if ((float) $l->carried_forward)<div class="text-xs font-normal text-danger">carries @money($l->carried_forward)</div>@endif</x-table.td>
                        <x-table.td align="right">@if ($l->isPosted())@money($l->paid_amount)@endif</x-table.td>
                        <x-table.td class="whitespace-nowrap">
                            @if ($run->isDraft()) @can('payroll.edit') <x-button size="sm" variant="ghost" icon="pencil" wire:click="edit({{ $l->id }})" aria-label="Edit line" /> @endcan @endif
                            @if (! $run->isDraft()) @can('payroll.print') <a href="{{ route('payroll.runs.slip', [$run, $l]) }}" target="_blank" class="text-xs text-link hover:underline">Slip</a> @endcan @endif
                        </x-table.td>
                    </tr>
                    @if ($editing === $l->id)
                        <tr wire:key="pe-{{ $l->id }}"><x-table.td colspan="11">
                            <form wire:submit="saveLine" class="flex flex-wrap items-end gap-3 rounded-md bg-sunken p-3">
                                <x-input label="Allowances" type="number" step="any" min="0" wire:model="allowances" />
                                <x-input :label="'Attendance deduction (calculated '.$l->calculated_attendance_deduction.')'" type="number" step="any" min="0" wire:model="attendance" />
                                <x-input label="Reason (if the deduction changes)" wire:model="reason" class="min-w-64 flex-1" />
                                <x-button type="submit" size="sm">Save</x-button>
                                <x-button size="sm" variant="ghost" wire:click="$set('editing', null)">Close</x-button>
                            </form>
                        </x-table.td></tr>
                    @endif
                @endforeach
                <tr class="font-semibold"><x-table.td colspan="8">Total</x-table.td><x-table.td align="right">@money($run->total_net)</x-table.td><x-table.td align="right">@if (! $run->isDraft())@money($run->total_paid)@endif</x-table.td><x-table.td></x-table.td></tr>
            </x-table>
        </x-card>
        @if ($run->isPosted() || in_array($run->status, ['partly_paid', 'paid'], true))
            <p class="mt-3 text-sm text-subtle">Pay net amounts with a Payment to the employee, allocated to this payroll. One or several payments, from any cash or bank account.</p>
        @endif
    @endif
    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <x-card title="Attachments" class="xl:col-span-2"><livewire:core::shared.attachments :model="$run" :key="'a-'.$run->id" /></x-card>
        <div class="space-y-6">
            <x-card title="Notes"><livewire:core::shared.notes :model="$run" :key="'n-'.$run->id" /></x-card>
            <x-card title="History"><livewire:core::shared.activity-history :model="$run" :key="'h-'.$run->id" /></x-card>
        </div>
    </div>
</div>
