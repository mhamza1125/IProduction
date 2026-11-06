<?php

namespace App\Modules\Finance\Services;

use App\Modules\Core\Services\DocumentNumber;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\Journal;
use App\Modules\Finance\Models\MoneyAccount;
use App\Support\Decimal;
use App\Support\Documents\DocumentException;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Writes journals. The only way accounting changes.
 *
 * Rules (specification, Posting map):
 *  - every journal is in one currency and must balance: debits = credits;
 *  - each line is a debit or a credit, never both, never zero;
 *  - lines on a cash/bank account must be in that account's currency;
 *  - journals and lines are never edited or deleted: reverse() posts the mirror image.
 * Must run inside the transaction that posts the source document.
 */
class JournalPoster
{
    public function __construct(private readonly DocumentNumber $numbers) {}

    public function post(JournalEntry $entry): Journal
    {
        if (DB::transactionLevel() === 0) {
            throw new LogicException('Journals must be posted inside a database transaction.');
        }

        $lines = array_values(array_filter($entry->lines, fn ($l) => ! Decimal::isZero($l['debit']) || ! Decimal::isZero($l['credit'])));

        if (count($lines) < 2) {
            throw new DocumentException('A journal needs at least one debit and one credit.');
        }

        $debits = Decimal::sum(array_column($lines, 'debit'));
        $credits = Decimal::sum(array_column($lines, 'credit'));

        if (Decimal::compare($debits, $credits) !== 0) {
            throw new DocumentException("The journal does not balance: debits {$debits}, credits {$credits} ({$entry->currency}).");
        }

        $resolved = [];
        foreach ($lines as $line) {
            if (Decimal::isNegative($line['debit']) || Decimal::isNegative($line['credit'])
                || (! Decimal::isZero($line['debit']) && ! Decimal::isZero($line['credit']))) {
                throw new LogicException('Each journal line is either a positive debit or a positive credit.');
            }

            $money = $line['account'] instanceof MoneyAccount ? $line['account'] : null;
            $account = match (true) {
                $money !== null => $money->glAccount()->firstOrFail(),
                $line['account'] instanceof GlAccount => $line['account'],
                default => GlAccount::system($line['account']),
            };

            if ($money !== null && $money->currency_code !== $entry->currency) {
                throw new DocumentException("{$money->name} is a {$money->currency_code} account; this journal is in {$entry->currency}.");
            }

            if (! $account->is_active) {
                throw new DocumentException("Account {$account->code} {$account->name} is inactive.");
            }

            $resolved[] = [$line, $account, $money];
        }

        $journal = Journal::query()->create([
            'number' => $this->numbers->next('journal', $entry->date),
            'journal_date' => $entry->date->format('Y-m-d'),
            'currency_code' => $entry->currency,
            'description' => mb_substr($entry->description, 0, 500),
            'source_type' => $entry->source?->getMorphClass(),
            'source_id' => $entry->source?->getKey(),
            'created_by' => auth()->id(),
        ]);

        foreach ($resolved as [$line, $account, $money]) {
            $ledgerable = $line['ledgerable'];

            $journal->lines()->create([
                'line_date' => $entry->date->format('Y-m-d'),
                'gl_account_id' => $account->id,
                'currency_code' => $entry->currency,
                'debit' => Decimal::round($line['debit']),
                'credit' => Decimal::round($line['credit']),
                'ledgerable_type' => $ledgerable?->getMorphClass(),
                'ledgerable_id' => $ledgerable?->getKey(),
                'party_id' => $ledgerable?->getAttribute('party_id'),
                'money_account_id' => $money?->id,
                'memo' => $line['memo'],
            ]);
        }

        return $journal;
    }

    /**
     * Posts the mirror image of a journal (debits become credits).
     */
    public function reverse(Journal $journal, CarbonInterface $date, string $reason, ?Model $source = null): Journal
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required to reverse a journal.');
        }

        if ($journal->reversal()->exists()) {
            throw new DocumentException("{$journal->number} is already reversed.");
        }

        if ($journal->reverses_journal_id !== null) {
            throw new DocumentException("{$journal->number} is itself a reversal.");
        }

        $entry = new JournalEntry($date, $journal->currency_code, "Reversal of {$journal->number}: {$reason}", $source ?? $journal->source);

        foreach ($journal->lines()->with(['account', 'moneyAccount', 'ledgerable'])->get() as $line) {
            $account = $line->moneyAccount ?? $line->account;
            Decimal::isZero($line->debit)
                ? $entry->debit($account, $line->credit, $line->ledgerable, $line->memo)
                : $entry->credit($account, $line->debit, $line->ledgerable, $line->memo);
        }

        $reversal = $this->post($entry);
        DB::table('journals')->where('id', $reversal->id)->update(['reverses_journal_id' => $journal->id]);

        activity('journals')->performedOn($journal)->event('reversed')->withProperties(['reason' => $reason, 'reversal' => $reversal->number])->log('Reversed');

        return $reversal->refresh();
    }
}
