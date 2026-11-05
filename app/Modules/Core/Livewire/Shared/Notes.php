<?php

namespace App\Modules\Core\Livewire\Shared;

use App\Support\Models\Attachable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Notes on any record using HasAttachmentsAndNotes. Anyone who can view the
 * record can read and add notes; notes are never edited, only added.
 */
class Notes extends Component
{
    /** @var Model&Attachable */
    #[Locked]
    public Model $model;

    public string $body = '';

    public function mount(Model $model): void
    {
        abort_unless($model instanceof Attachable, 500, 'Model does not support attachments and notes.');
        abort_unless(auth()->user()?->can($model->permissionKey().'.view'), 403);

        $this->model = $model;
    }

    public function add(): void
    {
        $this->authorize($this->model->permissionKey().'.view');
        $this->validate(['body' => ['required', 'string', 'max:5000']]);

        $this->model->notes()->create(['body' => $this->body, 'user_id' => auth()->id()]);
        $this->body = '';
    }

    public function render(): View
    {
        return view('core::livewire.shared.notes', [
            'notes' => $this->model->notes()->with('user:id,name')->get(),
        ]);
    }
}
