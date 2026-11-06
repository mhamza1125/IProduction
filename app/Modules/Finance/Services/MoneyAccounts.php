<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Illuminate\Support\Facades\DB;

class MoneyAccounts
{
    public function __construct(private readonly Ledgers $ledgers) {}

    /**
     * Creates a bank account with its own GL account under Banks (PKR) or
     * Foreign Currency Accounts (other currencies).
     *
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): MoneyAccount
    {
        return DB::transaction(function () use ($attributes) {
            $parent = GlAccount::system(($attributes['currency_code'] ?? 'PKR') === 'PKR' ? ($attributes['type'] === MoneyAccount::CASH ? 'cash' : 'banks') : 'foreign_currency_accounts');

            $gl = GlAccount::query()->create([
                'code' => $this->nextChildCode($parent),
                'name' => $attributes['name'],
                'type' => 'asset',
                'parent_id' => $parent->id,
            ]);

            return MoneyAccount::query()->create($attributes + ['gl_account_id' => $gl->id]);
        });
    }

    /**
     * Throws when paying $amount would take the account below zero, unless overdraft is allowed.
     */
    public function assertCanPay(MoneyAccount $account, string $amount): void
    {
        if ($account->allow_overdraft) {
            return;
        }

        // Serialise payments from the same account.
        MoneyAccount::query()->whereKey($account->id)->lockForUpdate()->first();

        $balance = $this->ledgers->moneyBalance($account);

        if (Decimal::compare($amount, $balance) > 0) {
            throw new DocumentException("{$account->name} has {$account->currency_code} {$balance}; cannot pay {$amount}.");
        }
    }

    private function nextChildCode(GlAccount $parent): string
    {
        $codes = GlAccount::withTrashed()->where('code', 'like', $parent->code.'-%')->pluck('code');
        $max = $codes->map(fn ($c) => (int) substr((string) $c, strlen($parent->code) + 1))->max() ?? 0;

        return $parent->code.'-'.str_pad((string) ($max + 1), 2, '0', STR_PAD_LEFT);
    }
}
