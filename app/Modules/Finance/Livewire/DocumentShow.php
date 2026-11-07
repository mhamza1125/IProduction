<?php

namespace App\Modules\Finance\Livewire;

use App\Modules\Finance\Models\Journal;
use App\Support\Documents\Document;
use App\Support\Livewire\HandlesDocumentActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Detail screen for a finance document: workflow buttons, details, an
 * optional body partial, linked journals, attachments, notes and history.
 */
abstract class DocumentShow extends Component
{
    use HandlesDocumentActions;

    abstract protected function model(): Model&Document;

    abstract protected function heading(): string;

    /** @return array<string, string|null> label => value */
    abstract protected function details(): array;

    abstract protected function editRoute(): string;

    abstract protected function indexRoute(): string;

    protected function printRoute(): ?string
    {
        return null;
    }

    /** A partial rendered above the details (lines, allocations...). */
    protected function body(): ?string
    {
        return null;
    }

    /** @return array<string, mixed> */
    protected function bodyData(): array
    {
        return [];
    }

    protected function document(): Model&Document
    {
        return $this->model();
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->model()->refresh();
    }

    public function delete(): void
    {
        $doc = $this->model();
        $this->authorize($doc->permissionKey().'.delete');
        abort_unless($doc->getAttribute('status') === 'draft', 403);
        if (method_exists($doc, 'lines')) {
            $doc->lines()->delete();
        }
        $doc->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute($this->indexRoute(), navigate: true);
    }

    public function render(): View
    {
        $doc = $this->model();

        return view('finance::livewire.document-show', [
            'doc' => $doc,
            'heading' => $this->heading(),
            'details' => $this->details(),
            'edit' => $this->editRoute(),
            'print' => $doc->getAttribute('status') === 'draft' ? null : $this->printRoute(),
            'body' => $this->body(),
            'journals' => Journal::query()->where('source_type', $doc->getMorphClass())->where('source_id', $doc->getKey())->get(),
        ] + $this->bodyData())->title($doc->getAttribute('number'));
    }
}
