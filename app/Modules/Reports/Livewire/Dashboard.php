<?php

namespace App\Modules\Reports\Livewire;

use App\Modules\Reports\Services\DashboardData;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    public function render(DashboardData $data): View
    {
        return view('reports::livewire.dashboard', ['cards' => $data->for(auth()->user())]);
    }
}
