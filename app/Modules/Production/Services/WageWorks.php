<?php

namespace App\Modules\Production\Services;

use App\Modules\People\Models\Employee;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcStage;
use App\Modules\Production\Models\PtcTransaction;
use App\Modules\Production\Models\WageWork;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Wage work (specification, Module 7 and 11): a Stage Receive by a wage
 * employee records accepted pieces at the route rate. HR or a supervisor
 * reviews it; approval posts Dr Inventory, Cr Wages and Salaries Payable and
 * adds the cost to the PTC. Rejected pieces are unpaid unless approved.
 */
class WageWorks
{
    public function __construct(private readonly ProductionAccounting $accounting, private readonly PtcCosting $costing) {}

    public function record(Employee $employee, Ptc $ptc, PtcStage $stage, PtcTransaction $receive, string $good, string $rejected, string $rate, CarbonInterface $date): WageWork
    {
        return WageWork::query()->create([
            'employee_id' => $employee->id, 'ptc_id' => $ptc->id, 'ptc_stage_id' => $stage->id, 'stage_id' => $stage->stage_id,
            'ptc_transaction_id' => $receive->id, 'work_date' => $date->format('Y-m-d'), 'quantity' => $good, 'rejected_qty' => $rejected,
            'rate' => $rate, 'amount' => Decimal::mul($good, $rate), 'status' => 'pending',
        ]);
    }

    /**
     * @param  string  $paidRejected  rejected pieces approved for payment (needs wage_work.override)
     */
    public function approve(WageWork $work, string $paidRejected = '0'): WageWork
    {
        return DB::transaction(function () use ($work, $paidRejected) {
            $work = WageWork::query()->with(['employee', 'ptc', 'stage'])->lockForUpdate()->findOrFail($work->id);
            if ($work->status !== 'pending') {
                throw new DocumentException('This wage work was already reviewed.');
            }
            if (Decimal::compare($paidRejected, '0') > 0) {
                if (! auth()->user()?->can('wage_work.override')) {
                    throw new DocumentException('Paying for rejected pieces needs permission.');
                }
                if (Decimal::compare($paidRejected, $work->rejected_qty) > 0) {
                    throw new DocumentException("Only {$work->rejected_qty} pieces were rejected.");
                }
            }

            $amount = Decimal::mul(Decimal::add($work->quantity, $paidRejected, 4), $work->rate);
            $journal = $this->accounting->labor($work->employee, $amount, $work->work_date, $work,
                "Wage work {$work->employee->name}".($work->ptc ? " {$work->ptc->number}" : '').($work->stage ? " {$work->stage->name}" : ''));

            $work->update(['status' => 'approved', 'paid_rejected_qty' => $paidRejected, 'amount' => $amount, 'approved_by' => auth()->id(), 'approved_at' => now(), 'journal_id' => $journal?->id]);

            if ($work->ptc) {
                $this->costing->add($work->ptc, 'wage', $amount, $work, $work->employee->name);
                if ($work->ptc->isFinished()) {
                    // Arrived after the PTC finished: goes straight onto the output lot.
                    app(PtcTransactions::class)->settleLateCost($work->ptc);
                }
            }

            return $work;
        });
    }

    public function reject(WageWork $work, string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
        DB::transaction(function () use ($work, $reason) {
            $work = WageWork::query()->lockForUpdate()->findOrFail($work->id);
            if ($work->status !== 'pending') {
                throw new DocumentException('This wage work was already reviewed.');
            }
            $work->update(['status' => 'rejected', 'notes' => $reason, 'approved_by' => auth()->id(), 'approved_at' => now()]);
        });
    }
}
