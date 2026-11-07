<?php

namespace App\Support\Livewire;

use App\Support\Format;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Shared list screen for documents: search (number, plus whatever
 * searchUsing() adds), status filter, date range, sorting, pagination and export.
 */
abstract class DocumentList extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $status = '';

    /** @return Builder<covariant Model> */
    abstract protected function baseQuery(): Builder;

    abstract protected function dateColumn(): string;

    abstract protected function permission(): string;

    abstract protected function title(): string;

    abstract protected function description(): string;

    abstract protected function showRoute(): string;

    abstract protected function createRoute(): ?string;

    /** @return list<string> */
    abstract protected function statuses(): array;

    /**
     * Columns after number and date: label => fn (Model) => string.
     *
     * @return array<string, callable(Model): string>
     */
    abstract protected function columns(): array;

    /**
     * Extra OR conditions for the search box (the number is always searched).
     *
     * @param  Builder<covariant Model>  $query
     */
    protected function searchUsing(Builder $query, string $like): void {}

    protected function searchPlaceholder(): string
    {
        return 'Search number, customer…';
    }

    /** @return list<string> */
    protected function rightAligned(): array
    {
        return [];
    }

    protected function sortable(): array
    {
        return ['number', $this->dateColumn()];
    }

    /** @return Builder<covariant Model> */
    private function query(): Builder
    {
        $query = $this->baseQuery()->when($this->status !== '', fn ($q) => $q->where('status', $this->status));
        $term = trim($this->search);
        if ($term !== '') {
            $like = '%'.addcslashes($term, '%_\\').'%';
            $query->where(function ($q) use ($like) {
                $q->where('number', 'like', $like);
                $this->searchUsing($q, $like);
            });
        }

        return $this->applyDateRange($query, $this->dateColumn());
    }

    protected function exportPermission(): string
    {
        return $this->permission().'.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('number');
    }

    protected function exportColumns(): array
    {
        $date = $this->dateColumn();

        return ['Number' => 'number', 'Date' => fn (Model $m) => Format::date($m->getAttribute($date))] + $this->columns() + ['Status' => 'status'];
    }

    public function render(): View
    {
        return view('livewire.document-list', [
            'rows' => $this->applySorting($this->query(), $this->dateColumn(), 'desc')->orderByDesc('id')->paginate($this->perPage),
            'columns' => $this->columns(),
            'right' => $this->rightAligned(),
            'statusOptions' => $this->statuses(),
            'heading' => $this->title(),
            'blurb' => $this->description(),
            'perm' => $this->permission(),
            'show' => $this->showRoute(),
            'create' => $this->createRoute(),
            'dateColumn' => $this->dateColumn(),
            'searchPlaceholder' => $this->searchPlaceholder(),
        ])->title($this->title());
    }
}
