<?php

namespace App\Modules\Core\Livewire\Shared;

use App\Support\Models\PermissionScoped;
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
