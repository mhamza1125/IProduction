<?php

namespace App\Modules\Finance\Livewire\Payments;

use App\Modules\Finance\Actions\AllocateAdvance;
use App\Modules\Finance\Actions\PostPayment;
use App\Modules\Finance\Models\Payment;
use App\Modules\Finance\Services\Payables;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Finance\Payable;
use App\Support\Livewire\HandlesDocumentActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class Show extends Component
{
    use HandlesDocumentActions;

    #[Locked]
    public Payment $payment;

    public string $allocateTo = '';

    public string $allocateAmount = '';

    public function mount(Payment $payment): void
    {
        $this->payment = $payment;
    }

    protected function document(): Model&Document
    {
        return $this->payment;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(PostPayment::class)($this->payment, [], $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->payment->refresh();
    }

    /** Applies part of an advance to an open bill / job / payroll. */
    public function allocate(AllocateAdvance $allocate): void
    {
        $this->authorize('payments.post');
        $this->validate(['allocateTo' => ['required', 'regex:/^[^:]+:\d+$/'], 'allocateAmount' => ['required', 'numeric', 'gt:0']]);

        [$type, $id] = explode(':', $this->allocateTo);
        $class = Relation::getMorphedModel($type) ?? $type;
        $target = class_exists($class) ? $class::query()->find((int) $id) : null;
        abort_unless($target instanceof Payable && $target instanceof Model, 404);

        try {
            $allocate($this->payment, $target, (string) $this->allocateAmount, now());
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }

        $this->reset('allocateTo', 'allocateAmount');
        $this->payment->refresh();
        $this->dispatch('toast', message: 'Advance applied.');
    }

    public function delete(): void
    {
        $this->authorize('payments.delete');
        abort_unless($this->payment->isDraft(), 403);
        $this->payment->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('finance.payments.index', navigate: true);
    }

    public function render(Payables $payables): View
    {
        $payee = $this->payment->payee;

        return view('finance::livewire.payments.show', [
            'allocations' => $this->payment->allocations()->with('allocatable')->get(),
            'open' => $payee && $this->payment->isPosted() && (float) $this->payment->unallocated() > 0 ? $payables->openFor($payee) : collect(),
        ])->title($this->payment->number);
    }
}
