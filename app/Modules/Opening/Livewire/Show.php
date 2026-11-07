<?php

namespace App\Modules\Opening\Livewire;

use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\Openings;
use App\Modules\Opening\Services\OpeningTypes;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentException;
use App\Support\Livewire\HandlesDocumentActions;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;

class Show extends Component
{
    use HandlesDocumentActions;

    #[Locked]
    public OpeningBatch $batch;

    #[Url(except: false)]
    public bool $errorsOnly = false;

    /** @var array<string, string> currency => old-system total */
    public array $controls = [];

    public string $newCurrency = '';

    public function mount(OpeningBatch $batch): void
    {
        $this->batch = $batch;
        $this->controls = $batch->control_totals ?? [];
    }

    protected function document(): Model&Document
    {
        return $this->batch;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(Openings::class)->post($this->batch, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshBatch(): void
    {
        $this->batch->refresh();
    }

    public function revalidate(Openings $openings): void
    {
        $this->authorize('opening.create');
        try {
            $errors = $openings->revalidate($this->batch);
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return;
        }
        $this->batch->refresh();
        $this->dispatch('toast', message: $errors ? "{$errors} rows still have errors." : 'All rows are valid.');
    }

    public function saveControls(Openings $openings): void
    {
        $this->authorize('opening.create');
        if ($this->newCurrency !== '') {
            $this->controls[strtoupper($this->newCurrency)] ??= '';
            $this->newCurrency = '';
        }
        try {
            $openings->setControlTotals($this->batch, $this->controls);
        } catch (DocumentException $e) {
            $this->addError('controls', $e->getMessage());

            return;
        }
        $this->batch->refresh();
        $this->dispatch('toast', message: 'Control totals saved.');
    }

    public function delete(): void
    {
        $this->authorize('opening.delete');
        abort_unless($this->batch->isDraft(), 403);
        $this->batch->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('opening.index', navigate: true);
    }

    public function render(OpeningTypes $types): View
    {
        $handler = $types->get($this->batch->type);

        return view('opening::livewire.show', [
            'columns' => $handler->columns(),
            'rows' => $this->batch->rows()->when($this->errorsOnly, fn ($q) => $q->whereNotNull('errors'))->limit(500)->get(),
        ])->title($this->batch->number);
    }
}
