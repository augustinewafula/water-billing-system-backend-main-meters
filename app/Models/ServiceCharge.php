<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ServiceCharge extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $fillable = ['from', 'to', 'amount', 'meter_charge_id'];

    /**
     * Get meter charge that owns the service charge
     * @return BelongsTo
     */
    public function meter_charge(): BelongsTo
    {
        return $this->belongsTo(MeterCharge::class);
    }
}
