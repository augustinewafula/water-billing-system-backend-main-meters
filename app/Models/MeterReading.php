<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeterReading extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['meter_id', 'previous_reading', 'current_reading', 'month', 'bill', 'status'];

    /**
     * Get meter that owns the meter reading
     * @return BelongsTo
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }
}
