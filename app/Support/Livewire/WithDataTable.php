<?php

namespace App\Support\Livewire;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;

/**
 * Search, sort and pagination for list screens.
 *
 * Usage in a component:
 *
 *   use WithDataTable;
 *
 *   protected function sortable(): array { return ['name', 'created_at']; }
 *
 *   public function render() {
 *       $query = $this->applySearch(Item::query(), ['code', 'name']);
 *       $rows  = $this->applySorting($query, 'name')->paginate($this->perPage);
 *   }
 *
 * Sorting only accepts columns listed in sortable(), so the URL cannot be used
 * to sort by arbitrary columns.
 */
trait WithDataTable
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortField = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDirection = 'asc';

    #[Url(as: 'per', except: 25)]
    public int $perPage = 25;

    /**
     * Columns the user may sort by.
     *
     * @return list<string>
     */
    abstract protected function sortable(): array;

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedPerPage(): void
    {
        if (! in_array($this->perPage, [10, 25, 50, 100], true)) {
            $this->perPage = 25;
        }

        $this->resetPage();
    }

    public function sortBy(string $field): void
    {
        if (! in_array($field, $this->sortable(), true)) {
            return;
        }

        $this->sortDirection = $this->sortField === $field && $this->sortDirection === 'asc' ? 'desc' : 'asc';
        $this->sortField = $field;
        $this->resetPage();
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @param  list<string>  $columns
     * @return TBuilder
     */
    protected function applySearch(Builder $query, array $columns): Builder
    {
        $term = trim($this->search);

        if ($term === '' || $columns === []) {
            return $query;
        }

        $like = '%'.addcslashes($term, '%_\\').'%';

        $query->where(function (Builder $q) use ($columns, $like) {
            foreach ($columns as $column) {
                $q->orWhere($column, 'like', $like);
            }
        });

        return $query;
    }

    /**
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    protected function applySorting(Builder $query, string $defaultField, string $defaultDirection = 'asc'): Builder
    {
        $field = in_array($this->sortField, $this->sortable(), true) ? $this->sortField : $defaultField;
        $direction = $this->sortField !== '' && in_array($this->sortField, $this->sortable(), true)
            ? ($this->sortDirection === 'desc' ? 'desc' : 'asc')
            : $defaultDirection;

        $query->orderBy($field, $direction);

        return $query;
    }
}
