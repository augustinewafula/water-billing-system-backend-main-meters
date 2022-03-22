<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MeterCharge extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $fillable = ['cost_per_unit', 'service_charge_in_percentage'];

    /**
     * Get the service charge for the meter charge.
     * @return HasMany
     */
    public function service_charges(): HasMany
    {
        return $this->hasMany(ServiceCharge::class);
    }
}
