<?php

namespace App\Support\Printing;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Renders a printable document as an HTML page (browser print) or a PDF download.
 *
 * In a controller, after checking "{resource}.print":
 *
 *   return app(Printer::class)->respond($request, 'sales::print.order', ['order' => $order], $order->number);
 *
 * The view extends the "print" layout (company header, footer text, signatures).
 * Add ?pdf=1 to the URL to download a PDF instead.
 */
class Printer
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function respond(Request $request, string $view, array $data, string $title, string $orientation = 'portrait'): Response
    {
        $data['title'] = $title;

        if ($request->boolean('pdf')) {
            return $this->pdf($view, $data, $orientation)->download($this->filename($title));
        }

        return response()->view($view, $data + ['forPdf' => false]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function pdf(string $view, array $data, string $orientation = 'portrait'): \Barryvdh\DomPDF\PDF
    {
        return Pdf::loadView($view, $data + ['forPdf' => true])
            ->setPaper('a4', $orientation)
            ->setOption(['isRemoteEnabled' => false, 'defaultFont' => 'DejaVu Sans']);
    }

    private function filename(string $title): string
    {
        return preg_replace('/[^A-Za-z0-9._-]+/', '-', $title).'.pdf';
    }
}
