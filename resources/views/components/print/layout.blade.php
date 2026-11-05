{{--
    Layout for printable documents (browser print and PDF). Plain CSS only: dompdf does not run Tailwind.

    <x-print.layout :title="$title" heading="Sales Order" :for-pdf="$forPdf">
        <x-slot:meta> ...number, date, party... </x-slot:meta>
        ...lines table...
        <x-slot:signatures>Prepared by|Approved by|Received by</x-slot:signatures>
    </x-print.layout>
--}}
@props(['title', 'heading', 'forPdf' => false, 'meta' => null, 'signatures' => null])
@php
    $settings = app(\App\Modules\Core\Services\Settings::class);
    $logo = $settings->get('company.logo_path');
    $logoData = $logo && Storage::disk('public')->exists($logo)
        ? 'data:image/png;base64,'.base64_encode(Storage::disk('public')->get($logo))
        : null;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        @page { margin: 18mm 14mm; }
        * { box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', Arial, sans-serif; font-size: 10.5px; color: #111; margin: 0; }
        .page { max-width: 190mm; margin: 0 auto; }
        .header { width: 100%; border-bottom: 2px solid #111; padding-bottom: 8px; margin-bottom: 12px; }
        .header td { vertical-align: top; }
        .company { font-size: 16px; font-weight: bold; }
        .muted { color: #555; }
        .doc-title { font-size: 15px; font-weight: bold; text-transform: uppercase; text-align: right; }
        table.lines { width: 100%; border-collapse: collapse; margin-top: 10px; }
        table.lines th { background: #f0f0f0; text-align: left; font-size: 9.5px; text-transform: uppercase; }
        table.lines th, table.lines td { border: 1px solid #bbb; padding: 4px 6px; }
        .num { text-align: right; white-space: nowrap; }
        .totals td { font-weight: bold; }
        table.meta { width: 100%; margin-bottom: 6px; }
        table.meta td { padding: 2px 0; vertical-align: top; }
        .signatures { width: 100%; margin-top: 50px; }
        .signatures td { width: 33%; text-align: center; padding: 0 10px; }
        .signatures span { display: block; border-top: 1px solid #111; padding-top: 4px; }
        .footer { margin-top: 24px; border-top: 1px solid #bbb; padding-top: 6px; font-size: 9px; color: #555; text-align: center; }
        .toolbar { padding: 10px; text-align: right; background: #f5f5f5; border-bottom: 1px solid #ddd; }
        .toolbar a, .toolbar button { font: 13px Arial, sans-serif; padding: 6px 12px; margin-left: 6px; cursor: pointer; }
        @media print { .toolbar { display: none; } }
    </style>
</head>
<body>
    @unless ($forPdf)
        <div class="toolbar">
            <button type="button" onclick="window.print()">Print</button>
            <a href="{{ request()->fullUrlWithQuery(['pdf' => 1]) }}">Download PDF</a>
        </div>
    @endunless

    <div class="page">
        <table class="header">
            <tr>
                <td style="width: 60%">
                    @if ($logoData)
                        <img src="{{ $logoData }}" alt="" style="height: 40px; margin-bottom: 4px;"><br>
                    @endif
                    <div class="company">{{ $settings->get('company.name') }}</div>
                    <div class="muted">{!! nl2br(e((string) $settings->get('company.address'))) !!}</div>
                    <div class="muted">
                        {{ collect([$settings->get('company.phone'), $settings->get('company.email')])->filter()->implode(' · ') }}
                    </div>
                    <div class="muted">
                        {{ collect(['NTN' => $settings->get('company.ntn'), 'STRN' => $settings->get('company.strn')])->filter()->map(fn ($v, $k) => "$k: $v")->implode(' · ') }}
                    </div>
                    @if ($settings->get('company.print_header'))
                        <div class="muted">{{ $settings->get('company.print_header') }}</div>
                    @endif
                </td>
                <td class="doc-title">{{ $heading }}</td>
            </tr>
        </table>

        @if ($meta)
            {{ $meta }}
        @endif

        {{ $slot }}

        @if ($signatures)
            <table class="signatures">
                <tr>
                    @foreach (explode('|', trim($signatures)) as $label)
                        <td><span>{{ $label }}</span></td>
                    @endforeach
                </tr>
            </table>
        @endif

        <div class="footer">
            {{ $settings->get('company.print_footer') }}
            <div>Printed {{ \App\Support\Format::datetime(now()) }} by {{ auth()->user()?->name }}</div>
        </div>
    </div>
</body>
</html>
