<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Payroll\Models\AttendanceSummary;
use App\Modules\Payroll\Models\PayrollLine;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Monthly attendance summaries, entered as one grid. Editable until the
 * employee's payroll for the month is posted; changes are logged.
 */
class Attendance
{
    /**
     * @param  array<int, array<string, mixed>>  $rows  employee id => fields
     */
    public function saveMonth(CarbonInterface $month, array $rows): int
    {
        $month = $month->copy()->startOfMonth();
        $saved = 0;

        DB::transaction(function () use ($month, $rows, &$saved) {
            foreach ($rows as $employeeId => $row) {
                $values = [];
                foreach (AttendanceSummary::FIELDS as $f) {
                    $v = $row[$f] ?? null;
                    if ($v !== null && $v !== '' && (! is_numeric($v) || Decimal::isNegative((string) $v))) {
                        throw new DocumentException('Attendance values must be zero or more.');
                    }
                    $values[$f] = $v === '' ? null : $v;
                }
                $values['notes'] = ($row['notes'] ?? '') ?: null;
                $empty = array_filter($values, fn ($v) => $v !== null) === [];
                $existing = AttendanceSummary::query()->where('employee_id', $employeeId)->whereDate('month', $month)->first();
                if ($empty && ! $existing) {
                    continue;
                }
                if ($this->locked((int) $employeeId, $month)) {
                    if ($existing && array_diff_assoc(array_map('strval', $values), array_map('strval', $existing->only(array_keys($values)))) !== []) {
                        throw new DocumentException('Payroll for '.$month->format('M Y').' is posted for one of these employees; their attendance cannot change.');
                    }

                    continue;
                }
                AttendanceSummary::query()->updateOrCreate(['employee_id' => $employeeId, 'month' => $month->format('Y-m-d')], $values);
                $saved++;
            }
        });

        return $saved;
    }

    public function locked(int $employeeId, CarbonInterface $month): bool
    {
        return PayrollLine::query()->where('employee_id', $employeeId)->whereDate('month', $month->copy()->startOfMonth())->whereNotNull('posted_key')->exists();
    }

    public function for(int $employeeId, CarbonInterface $month): ?AttendanceSummary
    {
        return AttendanceSummary::query()->where('employee_id', $employeeId)->whereDate('month', $month->copy()->startOfMonth())->first();
    }
}
