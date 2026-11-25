<?php

namespace App\Modules\Core\Livewire\Inbox;

use App\Models\User;
use App\Modules\Core\Services\ApprovalInbox;
use Illuminate\Contracts\View\View;
use Livewire\Component;

/** Top-bar bell: unread alerts plus what is waiting for the user's approval. */
class Bell extends Component
{
    public function render(ApprovalInbox $inbox): View
    {
        /** @var User $user */
        $user = auth()->user();

        return view('core::livewire.inbox.bell', [
            'alerts' => $user->unreadNotifications()->count(),
            'approvals' => $inbox->count($user),
        ]);
    }
}
