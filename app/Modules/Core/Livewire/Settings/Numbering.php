<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Modules\Core\Models\NumberSequence;
use App\Modules\Core\Services\DocumentNumber;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Prefix, year format, padding and next number for every document type.
 */
#[Title('Numbering')]
class Numbering extends Component
{
    #[Locked]
    public ?string $editing = null;

    public string $prefix = '';

    public string $separator = '-';

    public string $year_format = 'YY';

    public int $padding = 4;

    public int $next_number = 1;

    public bool $showForm = false;

    public function edit(string $type, ModuleRegistry $modules, DocumentNumber $numbers): void
    {
        abort_unless($modules->documentType($type) !== null, 404);

        $sequence = $numbers->sequence($type);
        $this->editing = $type;
        $this->prefix = $sequence->prefix;
        $this->separator = $sequence->separator;
        $this->year_format = $sequence->year_format;
        $this->padding = $sequence->padding;
        $this->next_number = $numbers->nextValue($type);
        $this->resetValidation();
        $this->showForm = true;
    }

    public function save(DocumentNumber $numbers): void
    {
        $this->authorize('settings.edit');
        abort_if($this->editing === null, 404);

        $data = $this->validate([
            'prefix' => ['required', 'string', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'separator' => ['required', Rule::in(['-', '/', '.'])],
            'year_format' => ['required', Rule::in(array_keys(NumberSequence::YEAR_FORMATS))],
            'padding' => ['required', 'integer', 'between:1,10'],
            'next_number' => ['required', 'integer', 'min:1'],
        ]);

        DB::transaction(function () use ($data, $numbers) {
            $numbers->sequence((string) $this->editing)->update(Arr::except($data, ['next_number']));
            $numbers->setNext((string) $this->editing, $data['next_number']);
        });

        $this->showForm = false;
        $this->dispatch('toast', message: 'Numbering saved.');
    }

    public function render(ModuleRegistry $modules, DocumentNumber $numbers): View
    {
        $rows = [];

        foreach ($modules->documentTypes() as $key => $type) {
            $rows[] = ['key' => $key, 'label' => $type->label, 'next' => $numbers->preview($key)];
        }

        return view('core::livewire.settings.numbering', [
            'rows' => $rows,
            'yearFormats' => NumberSequence::YEAR_FORMATS,
        ]);
    }
}
