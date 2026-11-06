<?php

namespace App\Modules\Sales\Http;

use App\Modules\Sales\Models\SalesOrder;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintController
{
    public function order(Request $request, SalesOrder $order, Printer $printer): Response
    {
        abort_if($order->isDraft(), 403, 'Approve the order before printing it.');

        return $printer->respond($request, 'sales::print.order', ['order' => $order->load(['customer', 'lines.variant.item.stockUnit'])], $order->number);
    }
}
