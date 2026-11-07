<?php

namespace App\Modules\Production\Livewire\Ptcs;

use App\Modules\Production\Models\PtcTransaction;

final class PtcTransactionLabel
{
    public static function for(string $type): string
    {
        return PtcTransaction::TYPES[$type] ?? ucfirst(str_replace('_', ' ', $type));
    }
}
