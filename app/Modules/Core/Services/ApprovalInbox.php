<?php

namespace App\Modules\Core\Services;

use App\Models\User;
use App\Support\Documents\Approvals;
use App\Support\Documents\Document;
use App\Support\Documents\DocumentCatalog;
use App\Support\Documents\DocumentStatus;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * Drafts waiting for approval that $user may approve: they have the approve
 * permission, the type needs approval, and someone else created the draft.
 */
class ApprovalInbox
{
    /**
     * Other things waiting for approval that are not documents (piece work...),
     * registered by their module: key => fn (User): array{label: string, count: int, url: string}.
     *
     * @var array<string, Closure(User): array{label: string, count: int, url: string}>
     */
    private array $queues = [];

    public function __construct(private readonly DocumentCatalog $catalog, private readonly Approvals $approvals) {}

    /** Keyed so repeated boots do not register twice. */
    public function register(string $key, Closure $queue): void
    {
        $this->queues[$key] = $queue;
    }

    /** @return list<array{label: string, count: int, url: string}> */
    public function queues(User $user): array
    {
        return array_values(array_filter(array_map(fn (Closure $q) => $q($user), $this->queues), fn (array $q) => $q['count'] > 0));
    }

    /** @return Collection<int, Model&Document> */
    public function for(User $user, int $limit = 200): Collection
    {
        $rows = collect();
        foreach ($this->catalog->classes() as $class) {
            /** @var Model&Document $sample */
            $sample = new $class;
            if (! $user->can($sample->permissionKey().'.approve') || ! $this->approvals->required($sample->documentType())) {
                continue;
            }
            $rows = $rows->concat($class::query()->where('status', DocumentStatus::DRAFT)
                ->where(fn ($q) => $q->whereNull('created_by')->orWhere('created_by', '!=', $user->id))
                ->oldest('created_at')->limit($limit)->get());
        }

        return $rows->sortBy('created_at')->values()->take($limit);
    }

    public function count(User $user): int
    {
        $count = 0;
        foreach ($this->catalog->classes() as $class) {
            $sample = new $class;
            if ($user->can($sample->permissionKey().'.approve') && $this->approvals->required($sample->documentType())) {
                $count += $class::query()->where('status', DocumentStatus::DRAFT)
                    ->where(fn ($q) => $q->whereNull('created_by')->orWhere('created_by', '!=', $user->id))->count();
            }
        }

        return $count + array_sum(array_column($this->queues($user), 'count'));
    }

    public function url(Model&Document $document): ?string
    {
        return $this->catalog->url($document);
    }
}
