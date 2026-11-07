<?php

namespace App\Modules\Production\Livewire\Processes;

use App\Modules\Production\Actions\PostMaterialProcess;
use App\Modules\Production\Models\MaterialProcess;
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
    public MaterialProcess $process;

    public function mount(MaterialProcess $process): void
    {
        $this->process = $process;
    }

    protected function document(): Model&Document
    {
        return $this->process;
    }

    protected function postDocument(?string $overrideReason): void
    {
        app(PostMaterialProcess::class)($this->process, $overrideReason);
    }

    #[On('document-updated')]
    public function refreshDocument(): void
    {
        $this->process->refresh();
    }

    public function delete(): void
    {
        $this->authorize('material_processes.delete');
        abort_unless($this->process->isDraft(), 403);
        $this->process->lines()->delete();
        $this->process->delete();
        session()->flash('toast', 'Draft deleted.');
        $this->redirectRoute('production.processes.index', navigate: true);
    }

    public function render(PostMaterialProcess $post): View
    {
        $process = $this->process->load(['lines.variant', 'lines.lot', 'lines.location', 'employee', 'contractor']);

        return view('production::livewire.processes.show', ['yield' => $post->yield($process)])->title($process->number);
    }
}
