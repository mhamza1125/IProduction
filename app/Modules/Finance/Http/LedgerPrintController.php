<?php

namespace App\Modules\Finance\Http;

use App\Modules\Finance\Services\LedgerSubjects;
use App\Support\Printing\Printer;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class LedgerPrintController
{
    public function __invoke(Request $request, Printer $printer): Response
    {
        $type = (string) $request->query('type');
        abort_unless(isset(LedgerSubjects::TYPES[$type]), 404);
        abort_unless($request->user()?->can(LedgerSubjects::TYPES[$type][2]), 403);

        $subject = LedgerSubjects::find($type, (int) $request->query('id'));
        abort_if($subject === null, 404);

        $from = $request->filled('from') ? Carbon::parse((string) $request->query('from')) : null;
        $to = $request->filled('to') ? Carbon::parse((string) $request->query('to')) : null;
        $currency = (string) $request->query('currency', 'PKR');

        return $printer->respond($request, 'finance::print.ledger', [
            'subject' => $subject,
            'typeLabel' => LedgerSubjects::TYPES[$type][0],
            'ledger' => LedgerSubjects::ledger($type, $subject, $from, $to, $currency),
            'currency' => LedgerSubjects::currency($type, $subject, $currency),
            'from' => $from,
            'to' => $to,
        ], 'Ledger '.($subject->getAttribute('code') ?? $subject->getAttribute('name')));
    }
}
