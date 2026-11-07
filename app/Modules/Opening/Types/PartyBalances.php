<?php

namespace App\Modules\Opening\Types;

use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\OpeningContext;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Opening party balances. The "owes us" side goes to the receivable/advance
 * account and the "we owe" side to the payable/advance account, tagged to
 * the party, against Opening Balance.
 */
abstract class PartyBalances extends BaseType
{
    /** @return class-string<Model> */
    abstract protected function partyClass(): string;

    abstract protected function codeColumn(): string;

    /** @return array{0: string, 1: string} side value => [account when they owe us, account when we owe them] */
    abstract protected function accounts(): array;

    /** @return array{0: string, 1: string} labels of the two sides as typed in the file: [owes us, we owe] */
    abstract protected function sides(): array;

    /** @param array<string, mixed> $row */
    protected function currency(Model $party, array $row): string
    {
        return 'PKR';
    }

    public function columns(): array
    {
        [$owes, $weOwe] = $this->sides();

        return [$this->codeColumn() => 'Code in this system', 'side' => "{$owes} or {$weOwe}", 'amount' => 'Amount (positive)', 'reference' => 'Old system reference (optional)'];
    }

    public function validate(array $row, OpeningContext $context): array
    {
        $e = [];
        $party = $this->party($this->partyClass(), $row[$this->codeColumn()]);
        if (! $party) {
            $e[] = 'Unknown code "'.$row[$this->codeColumn()].'".';
        } elseif (! $context->claim('party:'.$party->getKey())) {
            $e[] = 'This party appears twice in the file.';
        }
        if (! in_array(strtolower((string) $row['side']), array_map('strtolower', $this->sides()), true)) {
            $e[] = 'Side must be '.implode(' or ', $this->sides()).'.';
        }
        if (! $this->positive($row['amount'])) {
            $e[] = 'Amount must be above zero.';
        }

        return array_merge($e, $party ? $this->extraChecks($party, $row) : []);
    }

    /**
     * @param  array<string, mixed>  $row
     * @return list<string>
     */
    protected function extraChecks(Model $party, array $row): array
    {
        return [];
    }

    public function post(OpeningBatch $batch, Collection $rows): array
    {
        [$owesAccount, $weOweAccount] = $this->accounts();
        [$owesLabel] = $this->sides();
        $lines = [];
        foreach ($rows as $row) {
            $d = $row->data;
            $party = $this->party($this->partyClass(), $d[$this->codeColumn()]);
            $owes = strtolower((string) $d['side']) === strtolower($owesLabel);
            $currency = $this->currency($party, $d);
            $amount = Decimal::round((string) $d['amount']);
            $lines[] = ['account' => $owes ? $owesAccount : $weOweAccount, 'amount' => $amount, 'side' => $owes ? 'debit' : 'credit', 'party' => $party, 'currency' => $currency, 'memo' => $d['reference'] ?: 'Opening balance'];
            $this->add($currency, $owes ? $amount : Decimal::sub('0', $amount));
            $row->update(['result_type' => $party->getMorphClass(), 'result_id' => $party->getKey()]);
        }
        $this->journal($batch, $lines);

        return $this->takeTotals();
    }
}
