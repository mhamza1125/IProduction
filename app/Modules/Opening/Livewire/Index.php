<?php

namespace App\Modules\Opening\Livewire;

use App\Modules\Core\Services\Settings;
use App\Modules\Opening\Models\OpeningBatch;
use App\Modules\Opening\Services\Openings;
use App\Modules\Opening\Services\OpeningTypes;
use App\Support\Documents\DocumentException;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Title('Opening balances')]
class Index extends Component
{
    use WithFileUploads;

    public string $type = 'cash_banks';

    public string $batch_date = '';

    public ?TemporaryUploadedFile $file = null;

    public string $notes = '';

    public string $lock_reason = '';

    public function mount(): void
    {
        $this->batch_date = now()->subDay()->format('Y-m-d');
    }

    public function upload(Openings $openings): void
    {
        $this->authorize('opening.create');
        $this->validate([
            'type' => ['required', 'in:'.implode(',', array_keys(app(OpeningTypes::class)->options()))],
            'batch_date' => ['required', 'date'],
            'file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240'],
        ]);
        try {
            $batch = $openings->import($this->type, $this->file->getRealPath(), $this->file->getClientOriginalName(), $this->batch_date, $this->notes ?: null);
        } catch (DocumentException $e) {
            $this->addError('file', $e->getMessage());

            return;
        }
        session()->flash('toast', $batch->error_count ? "{$batch->number}: {$batch->error_count} rows have errors. Fix them before posting." : "{$batch->number}: all {$batch->row_count} rows are valid.");
        $this->redirectRoute('opening.show', $batch, navigate: true);
    }

    public function lock(Openings $openings): void
    {
        $this->authorize('opening.override');
        try {
            $openings->lockGoLive($this->lock_reason);
        } catch (DocumentException $e) {
            $this->addError('lock_reason', $e->getMessage());

            return;
        }
        $this->dispatch('toast', message: 'Go-live locked. Opening documents can no longer be added.');
    }

    public function render(Openings $openings, OpeningTypes $types): View
    {
        return view('opening::livewire.index', [
            'types' => $types->options(),
            'batches' => OpeningBatch::query()->latest('id')->get(),
            'locked' => $openings->locked(),
            'lockedAt' => app(Settings::class)->get('opening.locked_at'),
        ]);
    }
}
