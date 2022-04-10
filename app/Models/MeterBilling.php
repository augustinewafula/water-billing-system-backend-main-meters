<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterBilling extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['meter_reading_id', 'amount_paid', 'balance', 'mpesa_transaction_id', 'date_paid', 'amount_over_paid', 'credit'];

    /**
     * Get meter reading that owns the meter billing
     * @return BelongsTo
     */
    public function meter_reading(): BelongsTo
    {
        return $this->belongsTo(MeterReading::class);
    }
}
