<?php

namespace App\Models;

use App\Traits\Uuid;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class MeterReading extends Model
{
    use HasFactory, Uuid;

    public $incrementing = false;

    protected $keyType = 'uuid';

    protected $fillable = ['meter_id', 'previous_reading', 'current_reading', 'month', 'bill', 'service_fee', 'status', 'sms_sent', 'send_sms_at', 'bill_due_at'];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'send_sms_at' => 'datetime',
    ];

    /**
     * Get meter that owns the meter reading
     * @return BelongsTo
     */
    public function meter(): BelongsTo
    {
        return $this->belongsTo(Meter::class);
    }

    /**
     * Get meter that owns the meter reading
     * @return hasOne
     */
    public function user(): hasOne
    {
        return $this->hasOne(User::class, 'meter_id', 'meter_id');
    }

    /**
     * Get the meter_billings for the meter reading.
     * @return HasMany
     */
    public function meter_billings(): HasMany
    {
        return $this->hasMany(MeterBilling::class);
    }
}
