<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Finance\Models\Payment;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Payments')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $kind = '';

    protected function sortable(): array
    {
        return ['number', 'payment_date', 'amount'];
    }

    /** @return Builder<Payment> */
    private function query(): Builder
    {
        return $this->applyDateRange($this->applySearch(Payment::query()->with(['payee', 'moneyAccount', 'expenseAccount']), ['number', 'reference', 'notes'])
            ->when($this->kind !== '', fn ($q) => $q->where('payee_kind', $this->kind)), 'payment_date');
    }

    protected function exportPermission(): string
    {
        return 'payments.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return ['Number' => 'number', 'Date' => fn (Payment $p) => Format::date($p->payment_date), 'Paid to' => fn (Payment $p) => $p->payee?->getAttribute('name') ?? $p->expenseAccount?->name,
            'Account' => fn (Payment $p) => $p->moneyAccount->name, 'Amount' => 'amount', 'Allocated' => 'allocated_amount', 'Reference' => 'reference', 'Status' => 'status'];
    }

    public function render(): View
    {
        return view('finance::livewire.payments.index', [
            'payments' => $this->applySorting($this->query(), 'payment_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'kinds' => Payment::KINDS,
        ]);
    }
}
