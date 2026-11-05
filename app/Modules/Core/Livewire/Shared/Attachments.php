<?php

namespace App\Modules\Core\Livewire\Shared;

use App\Modules\Core\Models\Attachment;
use App\Support\Models\Attachable;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * File attachments for any record using HasAttachmentsAndNotes.
 * Viewing needs "{resource}.view"; uploading and deleting need "{resource}.edit".
 */
class Attachments extends Component
{
    use WithFileUploads;

    /** @var Model&Attachable */
    #[Locked]
    public Model $model;

    /** @var list<TemporaryUploadedFile> */
    public array $files = [];

    public function mount(Model $model): void
    {
        abort_unless($model instanceof Attachable, 500, 'Model does not support attachments and notes.');
        abort_unless(auth()->user()?->can($model->permissionKey().'.view'), 403);

        $this->model = $model;
    }

    public function upload(): void
    {
        $this->authorize($this->model->permissionKey().'.edit');

        $this->validate([
            'files' => ['required', 'array', 'max:10'],
            'files.*' => ['file', 'max:'.Attachment::MAX_KB, 'extensions:'.implode(',', Attachment::EXTENSIONS)],
        ]);

        foreach ($this->files as $file) {
            $path = $file->store('attachments/'.now()->format('Y/m'), 'local');

            $this->model->attachments()->create([
                'disk' => 'local',
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'uploaded_by' => auth()->id(),
            ]);
        }

        activity('attachments')->performedOn($this->model)->event('attached')
            ->withProperties(['files' => collect($this->files)->map->getClientOriginalName()->all()])
            ->log('Attached files');

        $this->files = [];
        $this->dispatch('toast', message: 'Files attached.');
    }

    public function delete(int $id): void
    {
        $this->authorize($this->model->permissionKey().'.edit');

        $attachment = $this->model->attachments()->findOrFail($id);
        $attachment->delete();

        activity('attachments')->performedOn($this->model)->event('detached')
            ->withProperties(['file' => $attachment->original_name])->log('Removed attachment');
    }

    public function render(): View
    {
        return view('core::livewire.shared.attachments', [
            'attachments' => $this->model->attachments()->with('uploader:id,name')->get(),
            'canEdit' => auth()->user()?->can($this->model->permissionKey().'.edit') ?? false,
        ]);
    }
}
