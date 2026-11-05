<?php

namespace App\Support\Documents;

use App\Modules\Core\Services\Settings;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * Settings > Period lock. Nothing dated on or before the lock date can be
 * posted or changed, except by a user with "period_lock.override" who gives a reason.
 */
class PeriodLock
{
    public function __construct(private readonly Settings $settings) {}

    public function lockDate(): ?CarbonImmutable
    {
        $date = $this->settings->get('period_lock.date');

        return $date ? CarbonImmutable::parse((string) $date)->endOfDay() : null;
    }

    public function isLocked(CarbonInterface $date): bool
    {
        $lock = $this->lockDate();

        return $lock !== null && $date->lessThanOrEqualTo($lock);
    }

    /**
     * @throws DocumentException when the date is locked and no valid override is given
     */
    public function assertOpen(CarbonInterface $date, ?string $overrideReason = null, ?object $subject = null): void
    {
        if (! $this->isLocked($date)) {
            return;
        }

        $user = auth()->user();
        $lockDate = $this->lockDate()?->format('d-m-Y');

        if ($user === null || ! $user->can('period_lock.override')) {
            throw new DocumentException("The period up to {$lockDate} is locked.");
        }

        if (blank($overrideReason)) {
            throw new DocumentException("The period up to {$lockDate} is locked. Give a reason to override the lock.");
        }

        activity('period_lock')
            ->performedOn($subject instanceof Model ? $subject : null)
            ->withProperties(['reason' => $overrideReason, 'date' => $date->format('Y-m-d'), 'lock_date' => $lockDate])
            ->event('override')
            ->log('Period lock overridden');
    }
}
