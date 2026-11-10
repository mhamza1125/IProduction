<?php

namespace App\Modules\Finance\Livewire\Ledgers;

use App\Modules\Core\Models\Currency;
use App\Modules\Finance\Models\GlAccount;
use App\Modules\Finance\Models\JournalLine;
use App\Modules\Finance\Models\MoneyAccount;
use App\Modules\Finance\Services\LedgerSubjects;
use App\Support\Documents\Reference;
use App\Support\Exports\ArrayExport;
use App\Support\Format;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ledger of a customer, vendor, employee, contractor, cash/bank account or GL
 * account: opening balance, every posted line with a running balance, closing.
 * Generated from posted journal lines only.
 */
#[Title('Ledgers')]
class Index extends Component
{
    #[Url]
    public string $type = 'customer';

    #[Url]
    public ?int $id = null;

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

    #[Url(except: 'PKR')]
    public string $currency = 'PKR';

    public function updatedType(): void
    {
        $this->id = null;
    }

    private function subject(): ?Model
    {
        if (! isset(LedgerSubjects::TYPES[$this->type]) || $this->id === null) {
            return null;
        }

        abort_unless(auth()->user()?->can(LedgerSubjects::TYPES[$this->type][2]), 403);

        return LedgerSubjects::find($this->type, $this->id);
    }

    /**
     * @return array{opening: string, rows: Collection<int, array{line: JournalLine, balance: string}>, closing: string, debits: string, credits: string}|null
     */
    private function ledger(?Model $subject): ?array
    {
        if ($subject === null) {
            return null;
        }

        return LedgerSubjects::ledger($this->type, $subject, $this->date($this->dateFrom), $this->date($this->dateTo), $this->currency);
    }

    private function date(string $value): ?Carbon
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? Carbon::parse($value) : null;
    }

    public function export(string $format = 'xlsx'): BinaryFileResponse
    {
        $this->authorize('ledgers.export');
        $subject = $this->subject();
        abort_if($subject === null, 404);
        $ledger = $this->ledger($subject);

        $rows = [['', '', 'Opening balance', '', '', $ledger['opening']]];
        foreach ($ledger['rows'] as $row) {
            $line = $row['line'];
            $rows[] = [Format::date($line->line_date), Reference::number($line->journal->source) ?? $line->journal->number, $line->memo ?? $line->journal->description, $line->debit, $line->credit, $row['balance']];
        }
        $rows[] = ['', '', 'Closing balance', $ledger['debits'], $ledger['credits'], $ledger['closing']];

        $name = str(LedgerSubjects::label($subject))->slug().'-ledger.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(new ArrayExport(['Date', 'Document', 'Description', 'Debit', 'Credit', 'Balance'], $rows), $name,
            $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX);
    }

    /**
     * @return array<int, string>
     */
    private function options(): array
    {
        if (! isset(LedgerSubjects::TYPES[$this->type]) || ! auth()->user()?->can(LedgerSubjects::TYPES[$this->type][2])) {
            return [];
        }

        $class = LedgerSubjects::TYPES[$this->type][1];

        return match ($this->type) {
            'account' => MoneyAccount::query()->orderBy('name')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->name} ({$a->currency_code})"])->all(),
            'gl' => GlAccount::query()->orderBy('code')->get()->mapWithKeys(fn ($a) => [$a->id => "{$a->code} {$a->name}"])->all(),
            default => $class::query()->orderBy('name')->get()->mapWithKeys(fn ($p) => [$p->id => $p->code.' · '.$p->name.($this->type === 'customer' ? " ({$p->currency_code})" : '')])->all(),
        };
    }

    public function render(): View
    {
        $subject = $this->subject();

        return view('finance::livewire.ledgers.index', [
            'types' => collect(LedgerSubjects::TYPES)->filter(fn ($t) => auth()->user()?->can($t[2]))->map(fn ($t) => $t[0])->all(),
            'options' => $this->options(),
            'subject' => $subject,
            'ledger' => $this->ledger($subject),
            'ledgerCurrency' => $subject ? LedgerSubjects::currency($this->type, $subject, $this->currency) : null,
            'currencies' => Currency::query()->orderByDesc('is_base')->orderBy('code')->pluck('code', 'code'),
        ]);
    }
}
