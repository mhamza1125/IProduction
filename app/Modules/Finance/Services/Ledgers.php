<?php

namespace App\Modules\Finance\Services;

use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\MoneyAccount;
use App\Support\Decimal;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Balances and ledgers, always calculated from posted journal lines.
 * Balance = debits − credits (positive: they owe us / we hold it;
 * negative: we owe them).
 */
class Ledgers
{
    /** Balance of a customer, vendor, employee or contractor profile, in its currency. */
    public function partyBalance(Model $profile, ?CarbonInterface $asOf = null): string
    {
        return $this->sum($this->profileLines($profile), $asOf);
    }

    /** Balance of a cash or bank account, in its currency. */
    public function moneyBalance(MoneyAccount $account, ?CarbonInterface $asOf = null): string
    {
        return $this->sum(JournalLine::query()->where('money_account_id', $account->id), $asOf);
    }

    /** Balance of a GL account in one currency. */
    public function accountBalance(GlAccount $account, string $currency = 'PKR', ?CarbonInterface $asOf = null): string
    {
        return $this->sum(JournalLine::query()->where('gl_account_id', $account->id)->where('currency_code', $currency), $asOf);
    }

    /**
     * @return array{opening: string, rows: Collection<int, array{line: JournalLine, balance: string}>, closing: string, debits: string, credits: string}
     */
    public function partyLedger(Model $profile, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return $this->ledger($this->profileLines($profile), $from, $to);
    }

    /**
     * @return array{opening: string, rows: Collection<int, array{line: JournalLine, balance: string}>, closing: string, debits: string, credits: string}
     */
    public function moneyLedger(MoneyAccount $account, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return $this->ledger(JournalLine::query()->where('money_account_id', $account->id), $from, $to);
    }

    /**
     * @return array{opening: string, rows: Collection<int, array{line: JournalLine, balance: string}>, closing: string, debits: string, credits: string}
     */
    public function accountLedger(GlAccount $account, string $currency, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        return $this->ledger(JournalLine::query()->where('gl_account_id', $account->id)->where('currency_code', $currency), $from, $to);
    }

    /**
     * @param  Builder<JournalLine>  $lines
     * @return array{opening: string, rows: Collection<int, array{line: JournalLine, balance: string}>, closing: string, debits: string, credits: string}
     */
    private function ledger(Builder $lines, ?CarbonInterface $from, ?CarbonInterface $to): array
    {
        $opening = $from ? $this->sum((clone $lines)->where('line_date', '<', $from->format('Y-m-d')), null) : '0.00';

        $period = (clone $lines)->with(['journal.source', 'account'])
            ->when($from, fn ($q) => $q->where('line_date', '>=', $from->format('Y-m-d')))
            ->when($to, fn ($q) => $q->where('line_date', '<=', $to->format('Y-m-d')))
            ->orderBy('line_date')->orderBy('journal_id')->orderBy('id')
            ->get();

        $running = $opening;
        $rows = $period->map(function (JournalLine $line) use (&$running) {
            $running = Decimal::add($running, Decimal::sub($line->debit, $line->credit));

            return ['line' => $line, 'balance' => $running];
        });

        return [
            'opening' => $opening,
            'rows' => $rows,
            'closing' => $running,
            'debits' => Decimal::sum($period->pluck('debit')),
            'credits' => Decimal::sum($period->pluck('credit')),
        ];
    }

    /**
     * @return Builder<JournalLine>
     */
    private function profileLines(Model $profile): Builder
    {
        return JournalLine::query()->where('ledgerable_type', $profile->getMorphClass())->where('ledgerable_id', $profile->getKey());
    }

    /**
     * @param  Builder<JournalLine>  $lines
     */
    private function sum(Builder $lines, ?CarbonInterface $asOf): string
    {
        $row = $lines->when($asOf, fn ($q) => $q->where('line_date', '<=', $asOf->format('Y-m-d')))
            ->selectRaw('COALESCE(SUM(debit), 0) AS d, COALESCE(SUM(credit), 0) AS c')->toBase()->first();

        return Decimal::sub((string) $row->d, (string) $row->c);
    }
}
