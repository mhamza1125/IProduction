<?php

namespace App\Modules\Finance\Models;

use App\Support\Documents\Document;
use App\Support\Documents\HasDocumentWorkflow;
use App\Support\Models\Attachable;
use App\Support\Models\HasAttachmentsAndNotes;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * A manual balanced debit/credit entry for adjustments and charges.
 *
 * @property int $id
 * @property Carbon $voucher_date
 * @property string $currency_code
 * @property string $description
 * @property string $total
 * @property int|null $journal_id
 */
class JournalVoucher extends Model implements Attachable, Document
{
    use HasAttachmentsAndNotes, HasDocumentWorkflow, LogsActivity;

    protected $fillable = ['number', 'voucher_date', 'currency_code', 'description', 'created_by'];

    protected function casts(): array
    {
        return ['voucher_date' => 'date', 'total' => 'decimal:2', 'approved_at' => 'datetime', 'posted_at' => 'datetime', 'cancelled_at' => 'datetime'];
    }

    /** @return list<string> */
    public function mutableWhenLocked(): array
    {
        return ['total', 'journal_id'];
    }

    public function documentType(): string
    {
        return 'journal_voucher';
    }

    public function documentDate(): CarbonInterface
    {
        return $this->voucher_date;
    }

    public function permissionKey(): string
    {
        return 'journal_vouchers';
    }

    public function showUrl(): string
    {
        return route('finance.vouchers.show', $this);
    }

    /** @return HasMany<JournalVoucherLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalVoucherLine::class)->orderBy('sort_order');
    }

    /** @return BelongsTo<Journal, $this> */
    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'description', 'total'])->logOnlyDirty()->useLogName('documents');
    }
}
