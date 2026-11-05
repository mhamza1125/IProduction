<?php

namespace App\Modules\Core\Livewire\Settings;

use App\Support\Documents\Approvals as ApprovalSettings;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Whether each document type needs approval before it can be posted.
 */
#[Title('Approvals')]
class Approvals extends Component
{
    /** @var array<string, bool> */
    public array $required = [];

    public function mount(ModuleRegistry $modules, ApprovalSettings $approvals): void
    {
        foreach ($modules->documentTypes() as $key => $type) {
            $this->required[$key] = $approvals->required($key);
        }
    }

    public function save(ModuleRegistry $modules, ApprovalSettings $approvals): void
    {
        $this->authorize('settings.edit');

        foreach (array_keys($modules->documentTypes()) as $key) {
            $approvals->set($key, (bool) ($this->required[$key] ?? true));
        }

        activity('settings')->event('updated')->withProperties(['approvals' => $this->required])->log('Changed approval settings');
        $this->dispatch('toast', message: 'Approval settings saved.');
    }

    public function render(ModuleRegistry $modules): View
    {
        return view('core::livewire.settings.approvals', ['types' => $modules->documentTypes()]);
    }
}
