<?php

namespace App\Modules\Core\Livewire\Inbox;

use App\Models\User;
use App\Modules\Core\Services\ApprovalInbox;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Everything waiting for the signed-in user: documents others created that
 * they may approve, other approval queues, and system alerts (failed backups
 * and jobs). Shown in the app only: the system sends no email.
 */
#[Title('Inbox')]
class Index extends Component
{
    use WithPagination;

    public function markRead(string $id): void
    {
        $this->user()->unreadNotifications()->whereKey($id)->update(['read_at' => now()]);
    }

    public function markAllRead(): void
    {
        $this->user()->unreadNotifications()->update(['read_at' => now()]);
        $this->dispatch('toast', message: 'All alerts marked as read.');
    }

    private function user(): User
    {
        /** @var User $user */
        $user = auth()->user();

        return $user;
    }

    public function render(ApprovalInbox $inbox, ModuleRegistry $modules): View
    {
        $user = $this->user();
        $types = $modules->documentTypes();

        return view('core::livewire.inbox.index', [
            'documents' => $inbox->for($user)->map(fn ($doc) => [
                'number' => $doc->getAttribute('number'),
                'type' => isset($types[$doc->documentType()]) ? $types[$doc->documentType()]->label : (string) str($doc->documentType())->headline(),
                'date' => $doc->documentDate(),
                'by' => $doc->getAttribute('created_by') ? User::query()->whereKey($doc->getAttribute('created_by'))->value('name') : null,
                'url' => $inbox->url($doc),
            ]),
            'queues' => $inbox->queues($user),
            'notifications' => $user->notifications()->latest()->paginate(20),
        ]);
    }
}
