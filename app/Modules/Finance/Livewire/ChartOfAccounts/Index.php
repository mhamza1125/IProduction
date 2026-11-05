<?php

namespace App\Modules\Finance\Livewire\ChartOfAccounts;

use App\Modules\Finance\Models\GlAccount;
use App\Support\Documents\DocumentException;
use App\Support\Livewire\WithDataTable;
use App\Support\Livewire\WithExport;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Title('Chart of accounts')]
class Index extends Component
{
    use WithDataTable, WithExport;

    #[Url(except: '')]
    public string $type = '';

    public bool $showForm = false;

    #[Locked]
    public ?int $editingId = null;

    public string $code = '';

    public string $name = '';

    public string $accountType = 'expense';

    public ?int $parent_id = null;

    public string $description = '';

    public bool $is_active = true;

    protected function sortable(): array
    {
        return ['code', 'name', 'type'];
    }

    public function create(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'code', 'name', 'parent_id', 'description', 'is_active');
        $this->accountType = $this->type !== '' ? $this->type : 'expense';
        $this->showForm = true;
    }

    public function edit(int $id): void
    {
        $account = GlAccount::query()->findOrFail($id);
        $this->resetValidation();
        $this->editingId = $account->id;
        $this->code = $account->code;
        $this->name = $account->name;
        $this->accountType = $account->type;
        $this->parent_id = $account->parent_id;
        $this->description = (string) $account->description;
        $this->is_active = $account->is_active;
        $this->showForm = true;
    }

    public function save(): void
    {
        $this->authorize($this->editingId ? 'chart_of_accounts.edit' : 'chart_of_accounts.create');

        $data = $this->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('gl_accounts', 'code')->ignore($this->editingId)],
            'name' => ['required', 'string', 'max:150'],
            'accountType' => ['required', Rule::in(array_keys(GlAccount::TYPES))],
            'parent_id' => ['nullable', Rule::exists('gl_accounts', 'id')->whereNull('deleted_at'), Rule::notIn([$this->editingId])],
            'description' => ['nullable', 'string', 'max:500'],
            'is_active' => ['boolean'],
        ]);

        try {
            ($this->editingId ? GlAccount::query()->findOrFail($this->editingId) : new GlAccount)->fill([
                'code' => $data['code'],
                'name' => $data['name'],
                'type' => $data['accountType'],
                'parent_id' => $data['parent_id'],
                'description' => $data['description'] ?: null,
                'is_active' => $data['is_active'],
            ])->save();
        } catch (DocumentException $e) {
            $this->addError('accountType', $e->getMessage());

            return;
        }

        $this->showForm = false;
        $this->dispatch('toast', message: 'Account saved.');
    }

    public function delete(int $id): void
    {
        $this->authorize('chart_of_accounts.delete');

        try {
            GlAccount::query()->findOrFail($id)->delete();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());
        }
    }

    /**
     * @return Builder<GlAccount>
     */
    private function query(): Builder
    {
        return $this->applySearch(
            GlAccount::query()->with('parent:id,code,name')->when($this->type !== '', fn ($q) => $q->where('type', $this->type)),
            ['code', 'name'],
        );
    }

    protected function exportPermission(): string
    {
        return 'chart_of_accounts.export';
    }

    protected function exportQuery(): Builder
    {
        return $this->query()->orderBy('code');
    }

    protected function exportColumns(): array
    {
        return [
            'Code' => 'code',
            'Name' => 'name',
            'Type' => fn (GlAccount $a) => GlAccount::TYPES[$a->type] ?? $a->type,
            'Parent' => fn (GlAccount $a) => $a->parent?->code,
            'System' => fn (GlAccount $a) => $a->system_key ? 'Yes' : 'No',
            'Active' => fn (GlAccount $a) => $a->is_active ? 'Yes' : 'No',
        ];
    }

    public function render(): View
    {
        return view('finance::livewire.chart-of-accounts.index', [
            'accounts' => $this->applySorting($this->query(), 'code')->paginate($this->perPage),
            'types' => GlAccount::TYPES,
            'parents' => GlAccount::query()->orderBy('code')->get(['id', 'code', 'name'])
                ->mapWithKeys(fn (GlAccount $a) => [$a->id => $a->code.' '.$a->name]),
        ]);
    }
}
