<?php

namespace App\Modules\Production\Services;

use App\Modules\Inventory\Models\Location;
use App\Modules\People\Models\Contractor;
use App\Modules\People\Models\Employee;
use App\Support\Documents\DocumentException;

/**
 * The employee or contractor a stage transaction is for.
 */
final readonly class Worker
{
    private function __construct(public Employee|Contractor $model) {}

    public static function from(?string $type, ?int $employeeId, ?int $contractorId): self
    {
        $model = match ($type) {
            'employee' => $employeeId ? Employee::query()->find($employeeId) : null,
            'contractor' => $contractorId ? Contractor::query()->find($contractorId) : null,
            default => null,
        };
        if ($model === null) {
            throw new DocumentException('Choose the employee or contractor.');
        }
        if (! $model->is_active) {
            throw new DocumentException("{$model->name} is inactive.");
        }

        return new self($model);
    }

    public function type(): string
    {
        return $this->model instanceof Employee ? 'employee' : 'contractor';
    }

    public function isContractor(): bool
    {
        return $this->model instanceof Contractor;
    }

    /** Where stock issued to this worker is held. */
    public function location(): Location
    {
        return $this->model instanceof Contractor ? Location::forContractor($this->model) : Location::productionFloor();
    }

    /** @return array{worker_type: string, employee_id: int|null, contractor_id: int|null} */
    public function columns(): array
    {
        return [
            'worker_type' => $this->type(),
            'employee_id' => $this->model instanceof Employee ? $this->model->id : null,
            'contractor_id' => $this->model instanceof Contractor ? $this->model->id : null,
        ];
    }
}
