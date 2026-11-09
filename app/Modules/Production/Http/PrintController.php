<?php

namespace App\Modules\Production\Http;

use App\Modules\Production\Models\ContractorJob;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Services\ContractorJobs;
use App\Modules\Production\Services\PtcCosting;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintController
{
    public function ptc(Request $request, Ptc $ptc, Printer $printer, PtcCosting $costing): Response
    {
        abort_if($ptc->isDraft(), 403, 'Approve the PTC before printing its card.');
        $ptc->load(['variant.item.stockUnit', 'orderLine.order.customer', 'route', 'responsible', 'outputLot', 'parent']);

        return $printer->respond($request, 'production::print.ptc', [
            'ptc' => $ptc,
            'stages' => $ptc->stages()->get(),
            'materials' => $ptc->materials()->with('variant.item')->get(),
            'transactions' => $ptc->transactions()->with(['ptcStage', 'employee', 'contractor', 'variant.item'])->get(),
            'reconciliation' => $costing->reconciliation($ptc),
        ], $ptc->number);
    }

    public function job(Request $request, ContractorJob $job, Printer $printer, ContractorJobs $jobs): Response
    {
        abort_if($job->isDraft(), 403, 'Approve the job before printing it.');
        $job->load(['contractor', 'lines.variant.item.stockUnit', 'lines.stage', 'materials.variant.item', 'orderLine.order']);

        return $printer->respond($request, 'production::print.job', ['job' => $job, 'reconciliation' => $jobs->reconciliation($job)], $job->number);
    }
}
