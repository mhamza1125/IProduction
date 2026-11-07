<?php

namespace App\Modules\Opening\Types;

use App\Modules\People\Models\Employee;

/** Employee balances in PKR: payable = we owe (wages payable), receivable = owes us (advances). */
class EmployeeBalances extends PartyBalances
{
    public function key(): string
    {
        return 'employee_balances';
    }

    public function label(): string
    {
        return 'Employee balances';
    }

    protected function partyClass(): string
    {
        return Employee::class;
    }

    protected function codeColumn(): string
    {
        return 'employee_code';
    }

    protected function accounts(): array
    {
        return ['employee_advances', 'wages_payable'];
    }

    protected function sides(): array
    {
        return ['receivable', 'payable'];
    }

    public function example(): array
    {
        return ['employee_code' => 'EMP-0001', 'side' => 'payable', 'amount' => '8000.00', 'reference' => 'October wages unpaid'];
    }
}
