<?php

namespace App\Modules\Finance\Models;

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
 * Money moved between two company accounts of the same currency.
 *
 * @property int $id
 * @property Carbon $transfer_date
 * @property int $from_account_id
 * @property int $to_account_id
 * @property string $amount
 * @property string|null $reference
 * @property string|null $notes
 * @property-read MoneyAccount $fromAccount
 * @property-read MoneyAccount $toAccount
 */
class Transfer extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'transfer_date', 'from_account_id', 'to_account_id', 'amount', 'reference', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['transfer_date' => 'date', 'amount' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return [];
    }

    public function documentType(): string
    {
        return 'transfer';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->transfer_date;
    }

    public function permissionKey(): string
    {
        return 'transfers';
    }

    public function showUrl(): string
    {
        return route('finance.transfers.show', $this);
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

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount'])->logOnlyDirty()->useLogName('documents');
    }
}
