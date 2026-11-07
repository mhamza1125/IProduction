<?php

namespace App\Modules\Delivery\Livewire\CreditNotes;

use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\InvoiceAllocation;
use App\Modules\Delivery\Services\CreditNotes;
use App\Support\Documents\Document;
use App\Support\Livewire\HandlesDocumentActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use HandlesDocumentActions;

    #[Locked]
    public CreditNote $note;

    public function mount(CreditNote $note): void
    {
        $this->note = $note;
    }

    protected function document(): Model&Document
    {
        return $this->note;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(CreditNotes::class)->post($this->note, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->note->refresh();
    }

    public function delete(): void
    {
        $this->authorize('credit_notes.delete');
        abort_unless($this->note->isDraft(), 403);
        $this->note->lines()->delete();
        $this->note->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('delivery.credit-notes.index', navigate: true);
    }

    public function render(): View
    {
        $n = $this->note->load(['customer', 'lines', 'invoice', 'deliveryReturn']);

        return view('delivery::livewire.credit-notes.show', [
            'allocations' => InvoiceAllocation::query()->with('invoice')->where('source_type', $n->getMorphClass())->where('source_id', $n->id)->get(),
        ])->title($n->number);
    }
}
