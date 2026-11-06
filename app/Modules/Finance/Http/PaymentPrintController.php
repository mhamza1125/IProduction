<?php

namespace App\Modules\Finance\Http;

use App\Modules\Finance\Models\Payment;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PaymentPrintController
{
    public function __invoke(Request $request, Payment $payment, Printer $printer): Response
    {
        return $printer->respond($request, 'finance::print.payment', [
            'payment' => $payment->load(['payee', 'expenseAccount', 'moneyAccount', 'method', 'allocations.allocatable']),
        ], $payment->number);
    }
}
