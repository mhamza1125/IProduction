<?php

namespace App\Modules\Purchasing\Http;

use App\Modules\Purchasing\Models\GoodsReceipt;
use App\Modules\Purchasing\Models\PurchaseOrder;
use App\Modules\Purchasing\Models\PurchaseReturn;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintController
{
    public function order(Request $request, PurchaseOrder $order, Printer $printer): Response
    {
        abort_if($order->isDraft(), 403, 'Approve the order before printing it.');

        return $printer->respond($request, 'purchasing::print.order', [
            'order' => $order->load(['vendor', 'lines.variant.item', 'lines.unit']),
        ], $order->number);
    }

    public function receipt(Request $request, GoodsReceipt $receipt, Printer $printer): Response
    {
        return $printer->respond($request, 'purchasing::print.receipt', [
            'receipt' => $receipt->load(['vendor', 'order', 'lines.variant.item', 'lines.orderLine.unit', 'lines.lot', 'lines.location', 'receiver']),
        ], $receipt->number);
    }

    public function return(Request $request, PurchaseReturn $return, Printer $printer): Response
    {
        return $printer->respond($request, 'purchasing::print.return', [
            'return' => $return->load(['vendor', 'receipt', 'lines.variant.item.stockUnit', 'lines.lot']),
        ], $return->number);
    }
}
