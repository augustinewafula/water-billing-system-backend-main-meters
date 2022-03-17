<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterToken extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['mpesa_transaction_id', 'token', 'service_fee', 'meter_id', 'units'];

    /**
     * Get meter that owns the meter reading
     * @return BelongsTo
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get mpesa transaction that owns the meter token
     * @return belongsTo
     */
    public function mpesa_transaction(): belongsTo
    {
        return $this->belongsTo(MpesaTransaction::class);
    }
}
