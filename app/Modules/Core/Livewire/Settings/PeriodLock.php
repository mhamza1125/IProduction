<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Services\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Lock date: nothing dated on or before it can be posted or changed.
 */
#[Title('Period lock')]
class PeriodLock extends Component
{
    public ?string $date = null;

    public string $reason = '';

    public function mount(Settings $settings): void
    {
        $this->date = $settings->get('period_lock.date');
        $this->reason = (string) $settings->get('period_lock.reason');
    }

    public function save(Settings $settings): void
    {
        $this->authorize('period_lock.edit');

        $data = $this->validate([
            'date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today'],
            'reason' => ['required', 'string', 'max:500'],
        ], [], ['date' => 'lock date']);

        $old = $settings->get('period_lock.date');

        $settings->setMany([
            'period_lock.date' => $data['date'],
            'period_lock.reason' => $data['reason'],
        ]);

        activity('period_lock')->event('updated')
            ->withProperties(['old' => ['date' => $old], 'attributes' => ['date' => $data['date']], 'reason' => $data['reason']])
            ->log($data['date'] ? 'Set the period lock date' : 'Removed the period lock');

        $this->dispatch('toast', message: 'Period lock saved.');
    }

    public function render(): View
    {
        return view('core::livewire.settings.period-lock');
    }
}
