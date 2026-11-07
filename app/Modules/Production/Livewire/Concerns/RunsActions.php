<?php

namespace App\Modules\Production\Livewire\Concerns;

use App\Support\Documents\DocumentException;

trait RunsActions
{
    /** Runs a business action and shows its message, or the rule it broke. */
    protected function attempt(callable $action, string $message): bool
    {
        try {
            $action();
        } catch (DocumentException $e) {
            $this->dispatch('toast', type: 'error', message: $e->getMessage());

            return false;
        }
        $this->dispatch('toast', message: $message);

        return true;
    }
}
