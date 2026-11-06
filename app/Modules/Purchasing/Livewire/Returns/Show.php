<?php

namespace App\Modules\Purchasing\Livewire\Returns;

use App\Modules\Purchasing\Actions\PostPurchaseReturn;
use App\Modules\Purchasing\Models\PurchaseReturn;
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
    public PurchaseReturn $return;

    public function mount(PurchaseReturn $return): void
    {
        $this->return = $return;
    }

    protected function document(): Model&Document
    {
        return $this->return;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(PostPurchaseReturn::class)($this->return, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->return->refresh();
    }

    public function delete(): void
    {
        $this->authorize('purchase_returns.delete');
        abort_unless($this->return->isDraft(), 403);
        $this->return->lines()->delete();
        $this->return->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('purchasing.returns.index', navigate: true);
    }

    public function render(): View
    {
        return view('purchasing::livewire.returns.show', [
            'lines' => $this->return->lines()->with(['variant.item.stockUnit', 'lot', 'location'])->get(),
        ])->title($this->return->number);
    }
}
