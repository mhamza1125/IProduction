<?php

namespace App\Modules\Opening\Types;

use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Support\Decimal;
use Illuminate\Support\Collection;

/** Cash and bank balances in each account's currency, against Opening Balance. */
class CashAndBanks extends BaseType
{
    public function key(): string
    {
        return 'cash_banks';
    }

    public function label(): string
    {
        return 'Cash and bank balances';
    }

    public function columns(): array
    {
        return ['account' => 'Account name in this system', 'currency' => 'Account currency (checked)', 'amount' => 'Balance (negative for an overdraft)'];
    }

    public function example(): array
    {
        return ['account' => 'Cash Book', 'currency' => 'PKR', 'amount' => '150000.00'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        $account = MoneyAccount::query()->where('name', $row['account'])->first();
        if (! $account) {
            $e[] = "Unknown account \"{$row['account']}\". Create it under Finance > Accounts first.";
        } else {
            if (strtoupper((string) $row['currency']) !== $account->currency_code) {
                $e[] = "{$account->name} is in {$account->currency_code}.";
            }
            if (! $context->claim('acc:'.$account->id)) {
                $e[] = 'This account appears twice in the file.';
            }
        }
        if (! is_numeric($row['amount']) || Decimal::isZero((string) $row['amount'])) {
            $e[] = 'Amount must be a number other than zero.';
        }

        return $e;
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        $lines = [];
        foreach ($rows as $row) {
            $account = MoneyAccount::query()->where('name', $row->data['account'])->firstOrFail();
            $amount = Decimal::round((string) $row->data['amount']);
            $negative = Decimal::isNegative($amount);
            $lines[] = ['account' => $account, 'amount' => $negative ? Decimal::sub('0', $amount) : $amount, 'side' => $negative ? 'credit' : 'debit', 'currency' => $account->currency_code];
            $this->add($account->currency_code, $amount);
            $row->update(['result_type' => $account->getMorphClass(), 'result_id' => $account->id]);
        }
        $this->journal($batch, $lines);

        return $this->takeTotals();
    }
}
