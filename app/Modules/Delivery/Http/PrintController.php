<?php

namespace App\Modules\Delivery\Http;

use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\Delivery;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Models\Invoice;
use App\Modules\Delivery\Services\Packing;
use App\Support\Printing\Printer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PrintController
{
    public function delivery(Request $request, Delivery $delivery, Printer $printer): Response
    {
        abort_if($delivery->isDraft() || $delivery->isCancelled(), 403, 'Allocate the delivery before printing it.');

        return $printer->respond($request, 'delivery::print.delivery', ['delivery' => $delivery->load(['customer', 'lines.variant.item.stockUnit', 'lines.orderLine.order', 'lines.allocations.lot'])], $delivery->number);
    }

    public function packing(Request $request, Delivery $delivery, Printer $printer, Packing $packing): Response
    {
        abort_if($delivery->isDraft() || $delivery->isCancelled(), 403, 'Allocate the delivery before printing its packing list.');
        $delivery->load(['customer', 'packingList.cartons.lines.deliveryLine.variant', 'packingList.cartons.lines.deliveryLine.orderLine.order']);

        return $printer->respond($request, 'delivery::print.packing', [
            'delivery' => $delivery,
            'totals' => $delivery->packingList ? $packing->totals($delivery->packingList) : ['cartons' => 0, 'pieces' => '0', 'gross' => '0', 'net' => '0'],
        ], $delivery->number.' packing list');
    }

    public function invoice(Request $request, Invoice $invoice, Printer $printer): Response
    {
        abort_if($invoice->isDraft(), 403, 'Approve the invoice before printing it.');

        return $printer->respond($request, 'delivery::print.invoice', ['invoice' => $invoice->load(['customer', 'lines.variant.item.stockUnit', 'lines.orderLine.order', 'lines.deliveryLine.delivery'])], $invoice->number);
    }

    public function return(Request $request, DeliveryReturn $return, Printer $printer): Response
    {
        abort_if($return->isDraft(), 403, 'Approve the return before printing it.');

        return $printer->respond($request, 'delivery::print.return', ['return' => $return->load(['customer', 'deliveryLine.delivery', 'deliveryLine.variant', 'deliveryLine.orderLine.order', 'lot', 'invoiceLine.invoice'])], $return->number);
    }

    public function creditNote(Request $request, CreditNote $note, Printer $printer): Response
    {
        abort_if($note->isDraft(), 403, 'Approve the credit note before printing it.');

        return $printer->respond($request, 'delivery::print.credit-note', ['note' => $note->load(['customer', 'lines', 'invoice'])], $note->number);
    }
}
