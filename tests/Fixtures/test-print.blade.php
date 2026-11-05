<x-print.layout :title="$title" heading="Test document" :for-pdf="$forPdf">
    <table class="lines"><tr><th>Item</th><th class="num">Qty</th></tr><tr><td>Glove</td><td class="num">10</td></tr></table>
    <x-slot:signatures>Prepared by|Approved by</x-slot:signatures>
</x-print.layout>
