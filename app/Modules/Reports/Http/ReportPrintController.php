<?php

namespace App\Modules\Reports\Http;

use App\Modules\Reports\Services\Reports;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ReportPrintController
{
    public function __invoke(Request $request, string $key, Reports $reports, Printer $printer): Response
    {
        $report = $reports->find($key) ?? abort(404);
        abort_unless($reports->allowed($report, $request->user()), 403);
        $filters = array_merge($report->defaults(), array_intersect_key((array) $request->query('f', []), $report->defaults()));
        $rows = $report->rows($filters);

        return $printer->respond($request, 'reports::print.report', ['report' => $report, 'rows' => $rows, 'filters' => $filters, 'totals' => $report->totals($rows)],
            $report->title(), $report->landscape() ? 'landscape' : 'portrait');
    }
}
