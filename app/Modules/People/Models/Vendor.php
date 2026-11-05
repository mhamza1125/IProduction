<?php

namespace App\Modules\People\Models;

use App\Modules\People\Concerns\IsPartyProfile;
use App\Modules\People\Database\Factories\VendorFactory;
use App\Support\Models\Attachable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A supplier. Vendor ledgers are in PKR.
 *
 * @property int $id
 * @property string|null $contact_person
 * @property string|null $phone
 * @property string|null $email
 * @property string|null $address
 * @property string|null $ntn
 * @property string|null $payment_terms
 * @property string|null $over_receipt_percent
 * @property string|null $notes
 */
#[UseFactory(VendorFactory::class)]
class Vendor extends Model implements Attachable
{
    /** @use HasFactory<VendorFactory> */
    use HasFactory, IsPartyProfile;

    protected $fillable = [
        'party_id', 'code', 'name', 'contact_person', 'phone', 'email', 'address', 'ntn',
        'payment_terms', 'over_receipt_percent', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'over_receipt_percent' => 'decimal:2'];
    }

    public static function codeType(): string
    {
        return 'vendor';
    }

    public function permissionKey(): string
    {
        return 'vendors';
    }
}
