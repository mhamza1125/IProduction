<?php

namespace App\Modules\Core\Notifications;

use Illuminate\Notifications\Notification;

/**
 * An alert shown in the app's notification bell (stored in the database; the
 * system sends no email and uses no outside service).
 */
class SystemAlert extends Notification
{
    public function __construct(
        public readonly string $title,
        public readonly string $message,
        public readonly string $level = 'danger',
        public readonly ?string $url = null,
        public readonly ?string $key = null,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /** @return array<string, string|null> */
    public function toArray(object $notifiable): array
    {
        return ['title' => $this->title, 'message' => $this->message, 'level' => $this->level, 'url' => $this->url, 'key' => $this->key];
    }
}
