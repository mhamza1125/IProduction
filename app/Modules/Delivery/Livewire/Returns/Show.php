<?php

namespace App\Modules\Delivery\Livewire\Returns;

use App\Modules\Core\Models\Stage;
use App\Modules\Delivery\Models\CreditNote;
use App\Modules\Delivery\Models\DeliveryReturn;
use App\Modules\Delivery\Services\DeliveryReturns;
use App\Modules\Production\Models\Rejection;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Livewire\HandlesDocumentActions;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use HandlesDocumentActions;

    #[Locked]
    public DeliveryReturn $return;

    public string $decision = 'restock';

    public string $quantity = '';

    public ?int $stage_id = null;

    public string $inspect_reason = '';

    public function mount(DeliveryReturn $return): void
    {
        $this->return = $return;
    }

    protected function document(): Model&Document
    {
        return $this->return;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(DeliveryReturns::class)->post($this->return, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->return->refresh();
    }

    public function delete(): void
    {
        $this->authorize('delivery_returns.delete');
        abort_unless($this->return->isDraft(), 403);
        $this->return->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('delivery.returns.index', navigate: true);
    }

    public function inspect(DeliveryReturns $returns): void
    {
        $this->authorize('delivery_returns.post');
        $this->validate(['decision' => ['required', 'in:restock,reject'], 'quantity' => ['required', 'numeric', 'gt:0'], 'stage_id' => ['nullable', 'exists:stages,id']]);
        try {
            if ($this->decision === 'restock') {
                $returns->restock($this->return, (string) $this->quantity, $this->stage_id, Carbon::now());
                $message = 'Restocked to Available.';
            } else {
                $rejection = $returns->reject($this->return, (string) $this->quantity, $this->inspect_reason, Carbon::now());
                $message = "Sent to rejections as {$rejection->number}: rework, scrap or sell it there.";
            }
        } catch (DocumentException $e) {
            $this->addError('quantity', $e->getMessage());

            return;
        }
        $this->reset('quantity', 'inspect_reason');
        $this->return->refresh();
        $this->dispatch('toast', message: $message);
    }

    public function render(): View
    {
        $r = $this->return->load(['customer', 'deliveryLine.delivery', 'deliveryLine.variant', 'deliveryLine.orderLine.order', 'invoiceLine.invoice', 'lot']);

        return view('delivery::livewire.returns.show', [
            'stages' => Stage::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id'),
            'rejections' => Rejection::query()->where('source_type', $r->getMorphClass())->where('source_id', $r->id)->get(),
            'credits' => CreditNote::query()->where('delivery_return_id', $r->id)->get(),
        ])->title($r->number);
    }
}
