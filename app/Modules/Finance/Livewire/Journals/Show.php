<?php

namespace App\Modules\Finance\Livewire\Journals;

use App\Modules\Finance\Models\Journal;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class Show extends Component
{
    #[Locked]
    public Journal $journal;

    public function mount(Journal $journal): void
    {
        $this->journal = $journal;
    }

    public function render(): View
    {
        return view('finance::livewire.journals.show', [
            'lines' => $this->journal->lines()->with(['account', 'ledgerable', 'moneyAccount'])->get(),
        ])->title($this->journal->number);
    }
}
