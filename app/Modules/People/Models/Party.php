<?php

namespace App\Modules\People\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The business or person behind one or more profiles (customer, vendor,
 * employee, contractor). Journal lines record both the party and the profile,
 * so one business acting as vendor and contractor can be seen as a whole.
 *
 * @property int $id
 * @property string $name
 */
class Party extends Model
{
    protected $fillable = ['name'];

    /** @return HasMany<Customer, $this> */
    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    /** @return HasMany<Vendor, $this> */
    public function vendors(): HasMany
    {
        return $this->hasMany(Vendor::class);
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    /** @return HasMany<Contractor, $this> */
    public function contractors(): HasMany
    {
        return $this->hasMany(Contractor::class);
    }
}
