<?php

namespace App\Modules\Sales\Http;

use App\Modules\Core\Services\Settings;
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

    /**
     * Proforma invoice: the order's lines and prices sent to the buyer before
     * shipment (often for an advance or a letter of credit). Drafts can be sent
     * for confirmation; cancelled orders cannot.
     */
    public function proforma(Request $request, SalesOrder $order, Printer $printer, Settings $settings): Response
    {
        abort_if($order->isCancelled(), 403, 'A cancelled order has no proforma invoice.');

        return $printer->respond($request, 'sales::print.proforma', [
            'order' => $order->load(['customer', 'lines.variant.item.stockUnit']),
            'validUntil' => now()->addDays(max(1, (int) $settings->get('sales.proforma_validity_days'))),
            'country' => (string) $settings->get('company.country'),
        ], 'Proforma '.$order->number);
    }
}
