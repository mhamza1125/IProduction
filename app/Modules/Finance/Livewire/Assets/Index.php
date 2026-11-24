<?php

namespace App\Modules\Finance\Livewire\Assets;

use App\Modules\Finance\Models\Asset;
use App\Modules\Finance\Services\Depreciation;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use App\Support\Format;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Asset register')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    public bool $showRun = false;

    /** Month to depreciate, yyyy-mm. */
    public string $runMonth = '';

    public function openRun(): void
    {
        $this->authorize('assets.post');
        $this->runMonth = now()->subMonthNoOverflow()->format('Y-m');
        $this->resetValidation();
        $this->showRun = true;
    }

    public function runDepreciation(Depreciation $depreciation): void
    {
        $this->authorize('assets.post');
        $this->validate(['runMonth' => ['required', 'date_format:Y-m']]);
        try {
            $journal = $depreciation->run(Carbon::createFromFormat('Y-m', $this->runMonth)->startOfMonth());
        } catch (DocumentException $e) {
            $this->addError('runMonth', $e->getMessage());

            return;
        }
        $this->showRun = false;
        $this->dispatch('toast', message: $journal ? "Depreciation posted ({$journal->number})." : 'Nothing to depreciate for that month.');
    }

    protected function sortable(): array
    {
        return ['code', 'name', 'purchase_date', 'value'];
    }

    /** @return Builder<Asset> */
    private function query(): Builder
    {
        $query = Asset::query()->with('assignedTo')->withSum('depreciations as accumulated', 'amount')->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(fn ($q) => $q->where('code', 'like', $like)->orWhere('name', 'like', $like)->orWhere('category', 'like', $like)
                ->orWhere('serial_number', 'like', $like)->orWhere('location', 'like', $like));
        }

        return $this->applyDateRange($query, 'purchase_date');
    }

    protected function exportPermission(): string
    {
        return 'assets.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        return ['Code' => 'code', 'Name' => 'name', 'Category' => 'category', 'Purchased' => fn (Asset $a) => Format::date($a->purchase_date), 'Value' => 'value',
            'Location' => 'location', 'Assigned to' => fn (Asset $a) => $a->assignedTo?->name, 'Serial' => 'serial_number', 'Status' => fn (Asset $a) => Asset::STATUSES[$a->status] ?? $a->status,
            'Accumulated depreciation' => fn (Asset $a) => (string) ($a->getAttributes()['accumulated'] ?? '0'),
            'Book value' => fn (Asset $a) => $a->value === null ? null : Decimal::sub((string) $a->value, (string) ($a->getAttributes()['accumulated'] ?? '0'))];
    }

    public function render(): View
    {
        return view('finance::livewire.assets.index', [
            'assets' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
            'preview' => $this->showRun && preg_match('/^\d{4}-\d{2}$/', $this->runMonth)
                ? app(Depreciation::class)->due(Carbon::createFromFormat('Y-m', $this->runMonth)->startOfMonth()) : collect(),
        ]);
    }
}
