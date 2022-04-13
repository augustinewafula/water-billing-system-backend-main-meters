<?php

namespace App\Models;

use App\Traits\HasUuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeterToken extends Model
{
    use HasFactory, HasUuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['mpesa_transaction_id', 'token', 'service_fee', 'meter_id', 'units', 'monthly_service_charge_deducted'];

    /**
     * Get meter that owns the meter reading
     * @return BelongsTo
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get meter that owns the meter token
     * @return hasOne
     */
    public function user(): HasOne
    {
        return $this->hasOne(User::class, 'meter_id', 'meter_id');
    }

    /**
     * Get mpesa transaction that owns the meter token
     * @return belongsTo
     */
    public function mpesa_transaction(): BelongsTo
    {
        return $this->belongsTo(MpesaTransaction::class);
    }
}
