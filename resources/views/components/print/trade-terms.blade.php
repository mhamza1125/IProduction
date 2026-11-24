{{--
    Closing block of commercial and proforma invoices: shipping marks, bank
    details, invoice terms and the statement of origin, each only when set.
    <x-print.trade-terms :marks="$invoice->shipping_marks" :origin-statement="true" />
--}}
@props(['marks' => null, 'originStatement' => false, 'validUntil' => null])
@php
    $settings = app(\App\Modules\Core\Services\Settings::class);
    $statement = $originStatement ? (string) $settings->get('company.origin_statement') : '';
    $statement = strtr($statement, ['{rex}' => (string) $settings->get('company.rex_number'), '{country}' => (string) $settings->get('company.country')]);
@endphp
@if ($validUntil)<p><strong>Valid until:</strong> {{ \App\Support\Format::date($validUntil) }}</p>@endif
@if ($marks)<p><strong>Shipping marks:</strong><br>{!! nl2br(e($marks)) !!}</p>@endif
@if ($settings->get('company.rex_number'))<p><strong>REX No:</strong> {{ $settings->get('company.rex_number') }}</p>@endif
@if ($settings->get('company.bank_details'))<p><strong>Bank details:</strong><br>{!! nl2br(e((string) $settings->get('company.bank_details'))) !!}</p>@endif
@if ($settings->get('company.invoice_terms'))<p><strong>Terms and conditions:</strong><br>{!! nl2br(e((string) $settings->get('company.invoice_terms'))) !!}</p>@endif
@if (trim($statement) !== '')<p><strong>Statement on origin:</strong><br>{!! nl2br(e($statement)) !!}</p>@endif
