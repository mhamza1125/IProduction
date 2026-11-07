<?php

namespace App\Modules\Production\Services;

use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Services\JournalEntry;
use App\Modules\Finance\Services\JournalPoster;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Production journals from the posting map. All in PKR.
 *   Wage work approved          Dr Inventory        Cr Wages and Salaries Payable
 *   Contractor receipt accepted Dr Inventory        Cr Contractor Payable
 *   Wastage or scrap            Dr Production Loss  Cr Inventory
 *   Recover charge (rejection)  Dr party payable    Cr Damage Recoveries
 */
class ProductionAccounting
{
    public function __construct(private readonly JournalPoster $poster) {}

    public function labor(Employee|Contractor $worker, string $amount, CarbonInterface $date, Model $source, string $description): ?Journal
    {
        if (Decimal::compare($amount, '0') <= 0) {
            return null;
        }
        $payable = $worker instanceof Employee ? 'wages_payable' : 'contractor_payable';

        return $this->poster->post((new JournalEntry($date, 'PKR', $description, $source))
            ->debit('inventory', $amount)
            ->credit($payable, $amount, $worker));
    }

    public function loss(string $amount, CarbonInterface $date, Model $source, string $description): ?Journal
    {
        if (Decimal::compare($amount, '0') <= 0) {
            return null;
        }

        return $this->poster->post((new JournalEntry($date, 'PKR', $description, $source))
            ->debit('production_loss', $amount)
            ->credit('inventory', $amount));
    }

    public function recoverCharge(Employee|Contractor $party, string $amount, CarbonInterface $date, Model $source, string $description): Journal
    {
        $payable = $party instanceof Employee ? 'wages_payable' : 'contractor_payable';

        return $this->poster->post((new JournalEntry($date, 'PKR', $description, $source))
            ->debit($payable, $amount, $party)
            ->credit('damage_recoveries', $amount));
    }
}
