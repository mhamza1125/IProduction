<?php

namespace App\Support\Documents;

use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Moves documents through Draft → Approved → Posted (or Cancelled).
 *
 * post() is the only way a document affects stock or accounts:
 *  - runs in one DB transaction: if any stock, journal or status change fails, nothing is saved;
 *  - locks the document row first, so double clicks and retries cannot post twice;
 *  - enforces approval settings and the period lock.
 *
 * Permission checks (e.g. "sales_orders.post") belong to the caller (Livewire
 * action or policy); this class enforces the business state rules.
 */
class DocumentWorkflow
{
    public function __construct(
        private readonly Approvals $approvals,
        private readonly PeriodLock $periodLock,
    ) {}

    /**
     * @template T of Model&Document
     *
     * @param  T  $document
     * @return T
     */
    public function approve(Model&Document $document): Model&Document
    {
        return $this->transition($document, function (Model&Document $doc) {
            $this->expectStatus($doc, [DocumentStatus::DRAFT], 'approved');

            $doc->forceFill(['status' => DocumentStatus::APPROVED, 'approved_by' => auth()->id(), 'approved_at' => now()])->save();
            $this->log($doc, 'approved');
        });
    }

    /**
     * @template T of Model&Document
     *
     * @param  T  $document
     * @return T
     */
    public function returnToDraft(Model&Document $document, string $reason): Model&Document
    {
        $this->requireReason($reason);

        return $this->transition($document, function (Model&Document $doc) use ($reason) {
            $this->expectStatus($doc, [DocumentStatus::APPROVED], 'returned to draft');

            $doc->forceFill(['status' => DocumentStatus::DRAFT, 'approved_by' => null, 'approved_at' => null])->save();
            $this->log($doc, 'returned to draft', ['reason' => $reason]);
        });
    }

    /**
     * Posts the document. $effects receives the locked, fresh document and
     * writes stock movements, journals and anything else; it runs inside
     * the same transaction.
     *
     * @template T of Model&Document
     *
     * @param  T  $document
     * @param  Closure(T): void  $effects
     * @param  string  $postedStatus  Status after posting, if the document uses its own (e.g. "in_progress").
     * @return T
     */
    public function post(Model&Document $document, Closure $effects, ?string $overrideReason = null, string $postedStatus = DocumentStatus::POSTED): Model&Document
    {
        return $this->transition($document, function (Model&Document $doc) use ($effects, $overrideReason, $postedStatus) {
            if ($doc->getAttribute('posted_at') !== null) {
                throw new DocumentException("{$doc->getAttribute('number')} is already posted.");
            }

            $allowed = $this->approvals->required($doc->documentType())
                ? [DocumentStatus::APPROVED]
                : [DocumentStatus::DRAFT, DocumentStatus::APPROVED];

            $this->expectStatus($doc, $allowed, 'posted');
            $this->periodLock->assertOpen($doc->documentDate(), $overrideReason, $doc);

            $effects($doc);

            $doc->forceFill(['status' => $postedStatus, 'posted_by' => auth()->id(), 'posted_at' => now()])->save();
            $this->log($doc, 'posted', $overrideReason ? ['override_reason' => $overrideReason] : []);
        });
    }

    /**
     * Cancels a document that has no posted effect.
     *
     * @template T of Model&Document
     *
     * @param  T  $document
     * @return T
     */
    public function cancel(Model&Document $document, string $reason): Model&Document
    {
        $this->requireReason($reason);

        return $this->transition($document, function (Model&Document $doc) use ($reason) {
            if ($doc->getAttribute('posted_at') !== null) {
                throw new DocumentException("{$doc->getAttribute('number')} is posted. Reverse it instead of cancelling.");
            }

            $this->expectStatus($doc, [DocumentStatus::DRAFT, DocumentStatus::APPROVED], 'cancelled');

            $doc->forceFill([
                'status' => DocumentStatus::CANCELLED,
                'cancelled_by' => auth()->id(),
                'cancelled_at' => now(),
                'cancel_reason' => $reason,
            ])->save();
            $this->log($doc, 'cancelled', ['reason' => $reason]);
        });
    }

    /**
     * Locks the document row, re-reads it, and runs $change in a transaction.
     *
     * @template T of Model&Document
     *
     * @param  T  $document
     * @param  Closure(T): void  $change
     * @return T
     */
    private function transition(Model&Document $document, Closure $change): Model&Document
    {
        DB::transaction(function () use ($document, $change) {
            /** @var T $locked */
            $locked = $document->newQuery()->lockForUpdate()->findOrFail($document->getKey());
            $change($locked);
        });

        return $document->refresh();
    }

    /**
     * @param  list<string>  $allowed
     */
    private function expectStatus(Model&Document $doc, array $allowed, string $action): void
    {
        $status = (string) $doc->getAttribute('status');

        if (! in_array($status, $allowed, true)) {
            $label = DocumentStatus::LABELS[$status] ?? str_replace('_', ' ', $status);

            throw new DocumentException("{$doc->getAttribute('number')} is {$label} and cannot be {$action}.");
        }
    }

    private function requireReason(string $reason): void
    {
        if (trim($reason) === '') {
            throw new DocumentException('A reason is required.');
        }
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function log(Model $doc, string $event, array $properties = []): void
    {
        activity('documents')->performedOn($doc)->event($event)->withProperties($properties)->log(ucfirst($event));
    }
}
