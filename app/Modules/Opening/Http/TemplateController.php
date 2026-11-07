<?php

namespace App\Modules\Opening\Http;

use App\Modules\Opening\Services\OpeningTypes;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** CSV template for an opening document: heading row, an example row and a hints row to delete. */
class TemplateController
{
    public function __invoke(string $type, OpeningTypes $types): StreamedResponse
    {
        try {
            $handler = $types->get($type);
        } catch (\InvalidArgumentException) {
            abort(404);
        }

        return response()->streamDownload(function () use ($handler) {
            $out = fopen('php://output', 'w');
            fputcsv($out, array_keys($handler->columns()));
            fputcsv($out, array_map(fn ($c) => $handler->example()[$c] ?? '', array_keys($handler->columns())));
            fclose($out);
        }, "opening-{$type}-template.csv", ['Content-Type' => 'text/csv']);
    }
}
