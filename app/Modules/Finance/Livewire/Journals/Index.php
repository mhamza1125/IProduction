<?php

namespace App\Modules\Finance\Livewire\Journals;

use App\Modules\Finance\Models\Journal;
use App\Support\Livewire\WithDataTable;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Every journal the system posted, newest first.
 */
#[Title('Journals')]
class Index extends Component
{
    use WithDataTable;

    protected function sortable(): array
    {
        return ['journal_date', 'number'];
    }

    public function render(): View
    {
        $query = $this->applyDateRange($this->applySearch(Journal::query()->with('source')->withSum('lines', 'debit'), ['number', 'description']), 'journal_date');

        return view('finance::livewire.journals.index', [
            'journals' => $this->applySorting($query, 'journal_date', 'desc')->orderByDesc('id')->paginate($this->perPage),
        ]);
    }
}
