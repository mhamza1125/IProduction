<?php

namespace App\Modules\Opening\Types;

use App\Modules\People\Models\Vendor;

/** Vendor balances in PKR: credit = we owe (AP), debit = vendor advance. */
class VendorBalances extends PartyBalances
{
    public function key(): string
    {
        return 'vendor_balances';
    }

    public function label(): string
    {
        return 'Vendor balances';
    }

    protected function partyClass(): string
    {
        return Vendor::class;
    }

    protected function codeColumn(): string
    {
        return 'vendor_code';
    }

    protected function accounts(): array
    {
        return ['vendor_advances', 'accounts_payable'];
    }

    protected function sides(): array
    {
        return ['debit', 'credit'];
    }

    public function example(): array
    {
        return ['vendor_code' => 'VEN-0001', 'side' => 'credit', 'amount' => '85000.00', 'reference' => 'Unpaid bills'];
    }
}
