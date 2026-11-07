<?php

namespace App\Modules\Payroll\Http;

use App\Modules\Payroll\Models\PayrollLine;
use App\Modules\Payroll\Models\PayrollRun;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintController
{
    public function sheet(Request $request, PayrollRun $run, Printer $printer): Response
    {
        abort_unless($request->user()?->can('salary.view'), 403);
        abort_if($run->isDraft(), 403, 'Approve the payroll before printing it.');

        return $printer->respond($request, 'payroll::print.sheet', ['run' => $run, 'lines' => $run->lines()->with('employee')->get()->sortBy('employee.name')], $run->number);
    }

    public function slip(Request $request, PayrollRun $run, PayrollLine $line, Printer $printer): Response
    {
        abort_unless($request->user()?->can('salary.view'), 403);
        abort_if($line->payroll_run_id !== $run->id || $run->isDraft(), 404);

        return $printer->respond($request, 'payroll::print.slip', ['run' => $run, 'line' => $line->load(['employee.department', 'employee.designation', 'items'])], 'Salary slip '.$line->employee->name.' '.$run->month->format('M Y'));
    }
}
