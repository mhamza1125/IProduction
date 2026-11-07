<?php

namespace App\Modules\Finance\Http;

use App\Modules\Finance\Models\JournalVoucher;
use App\Modules\Finance\Models\Receipt;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VoucherPrintController
{
    public function receipt(Request $request, Receipt $receipt, Printer $printer): Response
    {
        abort_if($receipt->isDraft(), 403, 'Approve the receipt before printing it.');

        return $printer->respond($request, 'finance::print.receipt', ['receipt' => $receipt->load(['customer', 'moneyAccount', 'deductions.account', 'allocations.receivable'])], $receipt->number);
    }

    public function voucher(Request $request, JournalVoucher $voucher, Printer $printer): Response
    {
        abort_if($voucher->isDraft(), 403, 'Approve the voucher before printing it.');

        return $printer->respond($request, 'finance::print.journal-voucher', ['voucher' => $voucher->load(['lines.account', 'lines.moneyAccount', 'lines.party'])], $voucher->number);
    }
}
