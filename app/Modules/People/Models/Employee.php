<?php

namespace App\Modules\People\Models;

use App\Modules\Core\Models\Lookup;
use App\Modules\People\Concerns\IsPartyProfile;
use App\Modules\People\Database\Factories\EmployeeFactory;
use App\Support\Models\Attachable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An in-house worker. Salary employees get a fixed monthly salary; wage
 * employees are paid for recorded work. Never merged with contractors.
 *
 * @property int $id
 * @property string|null $father_name
 * @property string|null $cnic
 * @property string|null $phone
 * @property string|null $address
 * @property int|null $department_id
 * @property int|null $designation_id
 * @property string $employee_type
 * @property Carbon $join_date
 * @property Carbon|null $exit_date
 * @property string|null $monthly_salary
 * @property int|null $payment_method_id
 * @property string|null $bank_name
 * @property string|null $bank_account_title
 * @property string|null $bank_account_number
 * @property string|null $iban
 */
#[UseFactory(EmployeeFactory::class)]
class Employee extends Model implements Attachable
{
    /** @use HasFactory<EmployeeFactory> */
    use HasFactory, IsPartyProfile;

    public const TYPE_SALARY = 'salary';

    public const TYPE_WAGE = 'wage';

    public const TYPES = [self::TYPE_SALARY => 'Salary', self::TYPE_WAGE => 'Wage'];

    protected $fillable = [
        'party_id', 'code', 'name', 'father_name', 'cnic', 'phone', 'address', 'department_id',
        'designation_id', 'employee_type', 'join_date', 'exit_date', 'monthly_salary',
        'payment_method_id', 'bank_name', 'bank_account_title', 'bank_account_number', 'iban', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'join_date' => 'date',
            'exit_date' => 'date',
            'monthly_salary' => 'decimal:2',
        ];
    }

    public static function codeType(): string
    {
        return 'employee';
    }

    public function permissionKey(): string
    {
        return 'employees';
    }

    /**
     * Fields hidden from the activity history of users without the permission.
     *
     * @return array<string, string>
     */
    public function sensitiveAttributes(): array
    {
        return ['monthly_salary' => 'salary.view'];
    }

    /** @return BelongsTo<Lookup, $this> */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'department_id')->withTrashed();
    }

    /** @return BelongsTo<Lookup, $this> */
    public function designation(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'designation_id')->withTrashed();
    }

    /** @return BelongsTo<Lookup, $this> */
    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(Lookup::class, 'payment_method_id')->withTrashed();
    }
}
