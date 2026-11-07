<?php

namespace App\Modules\Reports\Livewire;

use App\Modules\Reports\Services\Reports;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Reports')]
class Index extends Component
{
    public function render(Reports $reports): View
    {
        return view('reports::livewire.index', ['groups' => $reports->forUser(auth()->user())]);
    }
}
