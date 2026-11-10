<?php

namespace App\Modules\Core\Livewire\Shared;

use App\Support\Format;
use App\Support\Models\PermissionScoped;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Who did what to a record, newest first: <livewire:core::shared.activity-history :model="$record" />
 */
class ActivityHistory extends Component
{
    /** @var Model&PermissionScoped */
    #[Locked]
    public Model $model;

    public function mount(Model $model): void
    {
        abort_unless($model instanceof PermissionScoped, 500, 'Model must implement PermissionScoped.');
        abort_unless(auth()->user()?->can($model->permissionKey().'.view'), 403);

        $this->model = $model;
    }

    /**
     * A logged value as people read it: dates as dd-mm-yyyy in the company's
     * timezone (they are logged in UTC), empty as "—", true/false as Yes/No.
     */
    public static function display(mixed $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }
        if (is_bool($value)) {
            return $value ? 'Yes' : 'No';
        }
        if (! is_scalar($value)) {
            return (string) json_encode($value, JSON_UNESCAPED_UNICODE);
        }

        $text = (string) $value;
        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(\.\d+)?(Z|[+-]\d{2}:?\d{2})$/', $text)) {
            $moment = Carbon::parse($text)->setTimezone((string) config('app.timezone'));

            return $moment->format('H:i:s') === '00:00:00' ? Format::date($moment) : Format::datetime($moment);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $text)) {
            return Format::date($text);
        }

        return $text;
    }

    public function render(): View
    {
        // Fields such as salary or cost are hidden from users without the matching permission.
        $hidden = [];
        if (method_exists($this->model, 'sensitiveAttributes')) {
            foreach ($this->model->sensitiveAttributes() as $field => $permission) {
                if (! auth()->user()?->can($permission)) {
                    $hidden[] = $field;
                }
            }
        }

        return view('core::livewire.shared.activity-history', [
            'hidden' => $hidden,
            'activities' => Activity::query()
                ->where('subject_type', $this->model->getMorphClass())
                ->where('subject_id', $this->model->getKey())
                ->with('causer')
                ->latest('id')
                ->limit(100)
                ->get(),
        ]);
    }
}
