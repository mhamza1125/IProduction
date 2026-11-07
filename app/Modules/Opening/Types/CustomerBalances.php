<?php

namespace App\Modules\Opening\Types;

use App\Modules\People\Models\Customer;
use Illuminate\Database\Eloquent\Model;

/** Customer balances in the customer's currency: debit = owes us (AR), credit = advance. */
class CustomerBalances extends PartyBalances
{
    public function key(): string
    {
        return 'customer_balances';
    }

    public function label(): string
    {
        return 'Customer balances';
    }

    protected function partyClass(): string
    {
        return Customer::class;
    }

    protected function codeColumn(): string
    {
        return 'customer_code';
    }

    protected function accounts(): array
    {
        return ['accounts_receivable', 'customer_advances'];
    }

    protected function sides(): array
    {
        return ['debit', 'credit'];
    }

    public function columns(): array
    {
        return parent::columns() + ['currency' => "Customer's currency (checked)"];
    }

    public function example(): array
    {
        return ['customer_code' => 'CUS-0001', 'side' => 'debit', 'amount' => '12500.00', 'reference' => 'Open invoices 1041, 1052', 'currency' => 'USD'];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function extraChecks(Model $party, array $row): array
    {
        return strtoupper((string) $row['currency']) === $party->getAttribute('currency_code') ? [] : ["The customer's currency is {$party->getAttribute('currency_code')}."];
    }

    /** @param array<string, mixed> $row */
    protected function currency(Model $party, array $row): string
    {
        return (string) $party->getAttribute('currency_code');
    }
}
