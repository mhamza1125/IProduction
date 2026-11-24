<?php

namespace App\Modules\Production\Services;

use App\Modules\Catalog\Models\ItemVariant;
use App\Modules\Production\Models\Ptc;
use App\Modules\Production\Models\PtcStage;
use App\Modules\Production\Models\WorkerRate;
use Carbon\CarbonInterface;

/**
 * The piece rate to pay a worker for accepted pieces at a PTC stage, in order:
 *   1. the worker's own rate for this stage and this item,
 *   2. the worker's own rate for this stage (any item),
 *   3. the stage's standard rate copied onto the PTC from the route
 *      (wage rate for employees, contractor rate for contractors).
 * Within 1 and 2 the newest active rate effective on the work date wins.
 * Null means the work is not paid by piece (no standard rate and no individual rate).
 */
class StageRates
{
    /** @return array{rate: string|null, source: string} source: individual | standard | none */
    public function resolve(Worker $worker, Ptc $ptc, PtcStage $stage, CarbonInterface $date): array
    {
        $itemId = ItemVariant::withTrashed()->whereKey($ptc->item_variant_id)->value('item_id');

        $individual = WorkerRate::query()
            ->where('is_active', true)
            ->where('worker_type', $worker->type())
            ->where($worker->isContractor() ? 'contractor_id' : 'employee_id', $worker->model->id)
            ->where('stage_id', $stage->stage_id)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->where(fn ($q) => $q->whereNull('item_id')->when($itemId, fn ($q) => $q->orWhere('item_id', $itemId)))
            ->orderByRaw('item_id IS NULL')   // an item-specific rate beats an any-item rate
            ->orderByDesc('effective_from')
            ->orderByDesc('id')
            ->first();

        if ($individual !== null) {
            return ['rate' => (string) $individual->rate, 'source' => 'individual'];
        }

        $standard = $worker->isContractor() ? $stage->contractor_rate : $stage->wage_rate;

        return $standard === null ? ['rate' => null, 'source' => 'none'] : ['rate' => (string) $standard, 'source' => 'standard'];
    }
}
