<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Modules\Core\Console\SyncPermissions;
use App\Modules\Core\Notifications\SystemAlert;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Alerts for the people who run the system (Super Admins and users who can
 * edit settings), delivered to the notification bell inside the app.
 *
 * Alerts with the same key are sent once until read, so a job failing every
 * minute does not flood the bell.
 */
class Alerts
{
    public function toAdmins(string $title, string $message, string $level = 'danger', ?string $url = null, ?string $key = null): void
    {
        // An alert must never break the work that raised it (or hide its error).
        try {
            Log::log($level === 'danger' ? 'error' : 'warning', "{$title}: {$message}");

            $recipients = $this->admins()->reject(fn (User $u) => $key !== null && $u->unreadNotifications()->where('data->key', $key)->exists());
            Notification::send($recipients, new SystemAlert($title, $message, $level, $url, $key));
        } catch (Throwable $e) {
            Log::error('Could not record an alert: '.$e->getMessage());
        }
    }

    /** @return Collection<int, User> */
    public function admins(): Collection
    {
        return User::query()->where('is_active', true)
            ->where(fn ($q) => $q->role(SyncPermissions::ADMIN)->orWhere(fn ($q) => $q->permission('settings.edit')))
            ->get();
    }
}
