<?php

namespace App\Modules\Payroll\Livewire\Advances;

use App\Modules\Finance\Livewire\DocumentShow;
use App\Modules\Payroll\Models\SalaryAdvance;
use App\Modules\Payroll\Services\LoansAndAdvances;
use App\Support\Documents\Document;
use App\Support\Format;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;

class Show extends DocumentShow
{
    #[Locked]
    public SalaryAdvance $advance;

    public function mount(SalaryAdvance $advance): void
    {
        $this->advance = $advance->load(['employee', 'moneyAccount']);
    }

    protected function model(): Model&Document
    {
        return $this->advance;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(LoansAndAdvances::class)->postAdvance($this->advance, $overrideReason);
    }

    protected function heading(): string
    {
        return 'Salary advance '.$this->advance->number;
    }

    protected function details(): array
    {
        $a = $this->advance;

        return ['Employee' => $a->employee->name, 'Date' => Format::date($a->advance_date), 'Amount' => Format::money($a->amount), 'Salary month' => $a->salary_month->format('M Y'),
            'Paid from' => $a->moneyAccount->name, 'Recovered by payroll' => Format::money($a->recovered_amount), 'Balance' => Format::money($a->balance()), 'Notes' => $a->notes];
    }

    protected function editRoute(): string
    {
        return 'payroll.advances.edit';
    }

    protected function indexRoute(): string
    {
        return 'payroll.advances.index';
    }
}
