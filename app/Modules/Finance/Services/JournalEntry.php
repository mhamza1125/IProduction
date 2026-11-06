<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\MoneyAccount;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * A journal being built. Posting rules use it like:
 *
 *   $entry = new JournalEntry($date, 'PKR', 'GRN-26-0001 goods received', $grn);
 *   $entry->debit('inventory', '15000.00');
 *   $entry->credit('accounts_payable', '15000.00', ledgerable: $vendor);
 *   app(JournalPoster::class)->post($entry);
 *
 * Accounts are given as system keys ("inventory"), GlAccount models, or a
 * MoneyAccount (its own GL account; the line is tagged with the money account).
 */
final class JournalEntry
{
    /** @var list<array{account: string|GlAccount|MoneyAccount, debit: string, credit: string, ledgerable: Model|null, memo: string|null}> */
    public array $lines = [];

    public function __construct(
        public readonly CarbonInterface $date,
        public readonly string $currency,
        public readonly string $description,
        public readonly ?Model $source = null,
    ) {}

    public function debit(string|GlAccount|MoneyAccount $account, string $amount, ?Model $ledgerable = null, ?string $memo = null): self
    {
        $this->lines[] = ['account' => $account, 'debit' => $amount, 'credit' => '0', 'ledgerable' => $ledgerable, 'memo' => $memo];

        return $this;
    }

    public function credit(string|GlAccount|MoneyAccount $account, string $amount, ?Model $ledgerable = null, ?string $memo = null): self
    {
        $this->lines[] = ['account' => $account, 'debit' => '0', 'credit' => $amount, 'ledgerable' => $ledgerable, 'memo' => $memo];

        return $this;
    }
}
