<?php

namespace App\Modules\Opening\Types;

use App\Modules\People\Models\Contractor;

/** Contractor balances in PKR: credit = we owe, debit = advance given. */
class ContractorBalances extends PartyBalances
{
    public function key(): string
    {
        return 'contractor_balances';
    }

    public function label(): string
    {
        return 'Contractor balances';
    }

    protected function partyClass(): string
    {
        return Contractor::class;
    }

    protected function codeColumn(): string
    {
        return 'contractor_code';
    }

    protected function accounts(): array
    {
        return ['contractor_advances', 'contractor_payable'];
    }

    protected function sides(): array
    {
        return ['debit', 'credit'];
    }

    public function example(): array
    {
        return ['contractor_code' => 'CON-0001', 'side' => 'credit', 'amount' => '15000.00', 'reference' => ''];
    }
}
