<?php

namespace App\Modules\Inventory\Livewire\Adjustments;

use App\Modules\Inventory\Models\StockAdjustment;
use App\Support\Documents\DocumentStatus;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Stock adjustments')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    protected function sortable(): array
    {
        return ['number', 'adjustment_date'];
    }

    /** @return Builder<StockAdjustment> */
    private function query(): Builder
    {
        return $this->applyDateRange($this->applySearch(StockAdjustment::query()->with('reason')->withCount('lines'), ['number', 'notes'])
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status)), 'adjustment_date');
    }

    protected function exportPermission(): string
    {
        return 'stock_adjustments.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        return [
            'Number' => 'number',
            'Date' => fn (StockAdjustment $a) => Format::date($a->adjustment_date),
            'Reason' => fn (StockAdjustment $a) => $a->reason?->name,
            'Lines' => 'lines_count',
            'Status' => 'status',
            'Notes' => 'notes',
        ];
    }

    public function render(): View
    {
        return view('inventory::livewire.adjustments.index', [
            'adjustments' => $this->applySorting($this->query(), 'adjustment_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'statuses' => DocumentStatus::LABELS,
        ]);
    }
}
