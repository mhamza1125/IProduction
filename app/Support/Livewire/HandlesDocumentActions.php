<?php

namespace App\Support\Livewire;

use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Documents\DocumentWorkflow;
use App\Support\Documents\PeriodLock;
use Illuminate\Database\Eloquent\Model;

/**
 * Approve / return to draft / post / cancel buttons for a document screen.
 *
 * The component defines:
 *   protected function document(): Model&Document     the record
 *   protected function postDocument(?string $override): void   calls the module's Post action
 *
 * Reasons (cancel, return to draft, period-lock override) are typed into
 * $actionReason by the confirmation modal in <x-document-actions>.
 */
trait HandlesDocumentActions
{
    public string $actionReason = '';

    abstract protected function document(): Model&Document;

    abstract protected function postDocument(?string $overrideReason): void;

    public function approveDocument(): void
    {
        $doc = $this->document();
        $this->authorize($doc->permissionKey().'.approve');

        $this->runDocumentAction(fn () => app(DocumentWorkflow::class)->approve($doc), 'Approved.');
    }

    public function returnDocumentToDraft(): void
    {
        $doc = $this->document();
        $this->authorize($doc->permissionKey().'.approve');

        $this->runDocumentAction(fn () => app(DocumentWorkflow::class)->returnToDraft($doc, $this->actionReason), 'Returned to draft.');
    }

    public function postDocumentAction(): void
    {
        $doc = $this->document();
        $this->authorize($doc->permissionKey().'.post');

        $override = null;
        if (app(PeriodLock::class)->isLocked($doc->documentDate())) {
            $this->authorize('period_lock.override');
            $override = $this->actionReason;
        }

        $this->runDocumentAction(fn () => $this->postDocument($override), 'Posted.');
    }

    public function cancelDocument(): void
    {
        $doc = $this->document();
        $this->authorize($doc->permissionKey().'.cancel');

        $this->runDocumentAction(fn () => app(DocumentWorkflow::class)->cancel($doc, $this->actionReason), 'Cancelled.');
    }

    private function runDocumentAction(callable $action, string $message): void
    {
        try {
            $action();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->actionReason = '';
        $this->dispatch('toast', message: $message);
        $this->dispatch('document-updated');
    }
}
