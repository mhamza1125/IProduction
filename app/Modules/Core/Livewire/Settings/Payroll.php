<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Services\Settings;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Payroll defaults from the specification (Module 11). Payroll uses:
 * daily rate = monthly salary / salary day basis, hourly rate = daily rate / daily hours,
 * overtime = overtime hours × hourly rate × multiplier (salaried employees only;
 * wage employees are paid for the work they record).
 */
#[Title('Payroll settings')]
class Payroll extends Component
{
    public int $salary_day_basis = 30;

    public string $daily_hours = '8';

    public bool $deduct_unpaid_days = true;

    public bool $deduct_late_hours = true;

    public string $overtime_multiplier = '1';

    public function mount(Settings $settings): void
    {
        $this->salary_day_basis = (int) $settings->get('payroll.salary_day_basis', 30);
        $this->daily_hours = (string) $settings->get('payroll.daily_hours', '8');
        $this->deduct_unpaid_days = (bool) $settings->get('payroll.deduct_unpaid_days', true);
        $this->deduct_late_hours = (bool) $settings->get('payroll.deduct_late_hours', true);
        $this->overtime_multiplier = (string) $settings->get('payroll.overtime_multiplier', '1');
    }

    public function save(Settings $settings): void
    {
        $this->authorize('settings.edit');

        $data = $this->validate([
            'salary_day_basis' => ['required', 'integer', 'between:1,31'],
            'daily_hours' => ['required', 'numeric', 'gt:0', 'max:24'],
            'deduct_unpaid_days' => ['boolean'],
            'deduct_late_hours' => ['boolean'],
            'overtime_multiplier' => ['required', 'numeric', 'min:0', 'max:5'],
        ]);

        $settings->setMany([
            'payroll.salary_day_basis' => $data['salary_day_basis'],
            'payroll.daily_hours' => (string) $data['daily_hours'],
            'payroll.deduct_unpaid_days' => $data['deduct_unpaid_days'],
            'payroll.deduct_late_hours' => $data['deduct_late_hours'],
            'payroll.overtime_multiplier' => (string) $data['overtime_multiplier'],
        ]);

        activity('settings')->event('updated')->withProperties(['attributes' => $data])->log('Changed payroll settings');
        $this->dispatch('toast', message: 'Payroll settings saved.');
    }

    public function render(): View
    {
        return view('core::livewire.settings.payroll');
    }
}
