<?php

namespace App\Modules\People\Models;

use App\Modules\Core\Models\Currency;
use App\Modules\People\Concerns\IsPartyProfile;
use App\Modules\People\Database\Factories\CustomerFactory;
use App\Support\Models\Attachable;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A customer. Its ledger is kept in the customer's own currency.
 *
 * @property int $id
 * @property string $currency_code
 * @property string|null $company
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $country
 * @property string|null $billing_address
 * @property string|null $shipping_address
 * @property string|null $ntn
 * @property string|null $payment_terms
 * @property string|null $notes
 */
#[UseFactory(CustomerFactory::class)]
class Customer extends Model implements Attachable
{
    /** @use HasFactory<CustomerFactory> */
    use HasFactory, IsPartyProfile;

    protected $fillable = [
        'party_id', 'code', 'name', 'company', 'currency_code', 'email', 'phone', 'country',
        'billing_address', 'shipping_address', 'ntn', 'payment_terms', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public static function codeType(): string
    {
        return 'customer';
    }

    public function permissionKey(): string
    {
        return 'customers';
    }

    /** @return BelongsTo<Currency, $this> */
    public function currency(): BelongsTo
    {
        return $this->belongsTo(Currency::class, 'currency_code', 'code');
    }
}
