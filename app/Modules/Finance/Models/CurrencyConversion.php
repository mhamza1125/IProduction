<?php

namespace App\Modules\Finance\Models;

use App\Support\Decimal;
use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Foreign money converted into PKR. The PKR received is typed in; no exchange rate is fetched or required.
 *
 * @property int $id
 * @property Carbon $conversion_date
 * @property int $from_account_id
 * @property string $from_amount
 * @property int $to_account_id
 * @property string $pkr_received
 * @property string|null $deductions
 * @property int|null $deduction_account_id
 * @property string|null $reference
 * @property string|null $notes
 * @property-read MoneyAccount $fromAccount
 * @property-read MoneyAccount $toAccount
 */
class CurrencyConversion extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'conversion_date', 'from_account_id', 'from_amount', 'to_account_id', 'pkr_received', 'deductions', 'deduction_account_id', 'reference', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['conversion_date' => 'date', 'from_amount' => 'decimal:2', 'pkr_received' => 'decimal:2', 'deductions' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return [];
    }

    public function documentType(): string
    {
        return 'currency_conversion';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->conversion_date;
    }

    public function permissionKey(): string
    {
        return 'currency_conversions';
    }

    public function showUrl(): string
    {
        return route('finance.conversions.show', $this);
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function fromAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'from_account_id');
    }

    /** @return BelongsTo<MoneyAccount, $this> */
    public function toAccount(): BelongsTo
    {
        return $this->belongsTo(MoneyAccount::class, 'to_account_id');
    }

    /** @return BelongsTo<GlAccount, $this> */
    public function deductionAccount(): BelongsTo
    {
        return $this->belongsTo(GlAccount::class, 'deduction_account_id');
    }

    /** Information only: PKR (received + deductions) per foreign unit. */
    public function effectiveRate(): ?string
    {
        if (Decimal::compare($this->from_amount, '0') <= 0) {
            return null;
        }

        return Decimal::div(Decimal::add($this->pkr_received, $this->deductions ?? '0'), $this->from_amount, 4);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'from_amount', 'pkr_received', 'deductions'])->logOnlyDirty()->useLogName('documents');
    }
}
