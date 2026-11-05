<?php

namespace App\Support\Livewire;

use App\Support\Exports\QueryExport;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

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

    #[Url(as: 'from', except: '')]
    public string $dateFrom = '';

    #[Url(as: 'to', except: '')]
    public string $dateTo = '';

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

    public function updatedDateFrom(): void
    {
        $this->resetPage();
    }

    public function updatedDateTo(): void
    {
        $this->resetPage();
    }

    /**
     * Downloads the current filtered list. The component defines
     * exportPermission(), exportQuery() and exportColumns().
     */
    public function export(string $format = 'xlsx'): BinaryFileResponse
    {
        abort_unless(method_exists($this, 'exportQuery') && method_exists($this, 'exportColumns'), 404);

        $this->authorize($this->exportPermission());

        $writer = $format === 'csv' ? \Maatwebsite\Excel\Excel::CSV : \Maatwebsite\Excel\Excel::XLSX;
        $name = str($this->exportFilename())->slug().'-'.now()->format('Y-m-d').'.'.($format === 'csv' ? 'csv' : 'xlsx');

        return Excel::download(new QueryExport($this->exportQuery(), $this->exportColumns()), $name, $writer);
    }

    protected function exportPermission(): string
    {
        return 'export';
    }

    protected function exportFilename(): string
    {
        return class_basename(static::class);
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
     * Filters by the toolbar's date range (dd-mm-yyyy inputs store Y-m-d).
     *
     * @template TBuilder of Builder
     *
     * @param  TBuilder  $query
     * @return TBuilder
     */
    protected function applyDateRange(Builder $query, string $column): Builder
    {
        if ($this->validDate($this->dateFrom)) {
            $query->whereDate($column, '>=', $this->dateFrom);
        }

        if ($this->validDate($this->dateTo)) {
            $query->whereDate($column, '<=', $this->dateTo);
        }

        return $query;
    }

    private function validDate(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) === 1 && checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4));
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
