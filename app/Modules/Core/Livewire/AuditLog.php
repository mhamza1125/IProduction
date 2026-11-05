<?php

namespace App\Modules\Core\Livewire;

use App\Models\User;
use App\Support\Livewire\WithDataTable;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Spatie\Activitylog\Models\Activity;

/**
 * Everything the activity log recorded: who, when, what, old and new values, reasons.
 */
#[Title('Audit log')]
class AuditLog extends Component
{
    use WithDataTable;

    #[Url(except: '')]
    public string $user = '';

    #[Url(except: '')]
    public string $event = '';

    protected function sortable(): array
    {
        return ['created_at'];
    }

    protected function query(): Builder
    {
        $query = Activity::query()->with(['causer', 'subject']);

        if ($this->user !== '') {
            $query->where('causer_type', (new User)->getMorphClass())->where('causer_id', (int) $this->user);
        }

        if ($this->event !== '') {
            $query->where('event', $this->event);
        }

        return $this->applyDateRange($this->applySearch($query, ['description', 'subject_type', 'properties']), 'created_at');
    }

    protected function exportPermission(): string
    {
        return 'audit_log.export';
    }

    /**
     * @return \Illuminate\Database\Eloquent\Builder<Activity>
     */
    protected function exportQuery(): \Illuminate\Database\Eloquent\Builder
    {
        /** @var \Illuminate\Database\Eloquent\Builder<Activity> $query */
        $query = $this->query();

        return $query->latest('id');
    }

    /**
     * @return array<string, \Closure(Activity): mixed>
     */
    protected function exportColumns(): array
    {
        return [
            'Date' => fn (Activity $a) => $a->created_at?->format('d-m-Y H:i'),
            'User' => fn (Activity $a) => $a->causer?->getAttribute('name'),
            'Event' => fn (Activity $a) => $a->event,
            'Description' => fn (Activity $a) => $a->description,
            'Record' => fn (Activity $a) => class_basename((string) $a->subject_type).' #'.$a->subject_id,
            'Details' => fn (Activity $a) => json_encode($a->properties),
        ];
    }

    public function render(): View
    {
        return view('core::livewire.audit-log', [
            'activities' => $this->applySorting($this->query(), 'created_at', 'desc')->orderByDesc('id')->paginate($this->perPage),
            'users' => User::query()->orderBy('name')->pluck('name', 'id'),
            'events' => Activity::query()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event'),
        ]);
    }
}
