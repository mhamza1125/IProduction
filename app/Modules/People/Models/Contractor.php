<?php

namespace App\Modules\People\Models;

use App\Modules\People\Concerns\IsPartyProfile;
use App\Modules\People\Database\Factories\ContractorFactory;
use App\Support\Models\Attachable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * An external business or person that takes jobs and is paid for agreed work.
 * Contractor ledgers are in PKR. Stock held by a contractor still belongs to the company.
 *
 * @property int $id
 * @property string|null $contact_person
 * @property string|null $cnic_ntn
 * @property string|null $phone
 * @property string|null $address
 * @property list<string>|null $work_types
 * @property string|null $notes
 */
#[UseFactory(ContractorFactory::class)]
class Contractor extends Model implements Attachable
{
    /** @use HasFactory<ContractorFactory> */
    use HasFactory, IsPartyProfile;

    protected $fillable = [
        'party_id', 'code', 'name', 'contact_person', 'cnic_ntn', 'phone', 'address', 'work_types', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'work_types' => 'array'];
    }

    public static function codeType(): string
    {
        return 'contractor';
    }

    public function permissionKey(): string
    {
        return 'contractors';
    }
}
